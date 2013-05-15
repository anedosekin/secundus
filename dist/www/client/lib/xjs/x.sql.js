X.sql = (function() {
var rez = {
	nodeToString:function(node) {
		if(!node) return 'error';
		return node.parent ? (node.parent.alias+'.'+node.$.name) : node;
	},
	collectJoins: function(table_node, cut_link) {
		// T1->T2->T3
		// T1 left join (T2 left join T3 on T3.rid = T2.rel) on T2.rid=T1.rel
		// it's returned as [T1, [T2, T3]]
		var ret = [];
		for(var i in table_node) {
			var rel = table_node[i];
			if(rel && rel.joins) { //it's rel
				for(var j in rel.joins) { //it's rel params
					ret.push(this.collectJoins(rel.joins[j], cut_link));
					ret.push(') ON '+this.joinCondition(rel.joins[j].condition, cut_link))
				}
			}
		}
		function table_id(node) {
			return node.$.name + ' ' + node.alias;
		}
		
		if(ret.length == 0) {
			//no subjoins -> return table as it is
				return table_id(table_node);
		}
		ret.unshift('('+table_id(table_node)+' LEFT OUTER JOIN '); // if we have joins, add table to first element in join sequence
		return ret.join('');
	},
	/*
		All quotes supplied in values
	*/
	joinCondition: function(conds, cut) {
		var rez = [];
		for(var i=0;i<conds.length;++i) {
			var cond = conds[i];
			var value = cond.value ? '?' : this.nodeToString(cond.here)
			if(value==='?') {
				cut.push(cond.value);
			}
			rez.push(this.nodeToString(cond.there) + '=' + value);
		}
		return rez.join(' AND ');
	},
	linkedCondition:function(conds, cut) {
		var rez = [];
		for(var i=0;i<conds.length;++i) {
			var cond = conds[i];
			cut.push(cond.value ? cond.value : this.nodeToString(cond.here));
			rez.push(this.nodeToString(cond.there) + '=?');
		}
		return rez.join(' AND ');
	},
	keyCondition: function(key, cut) {
		var rez = [];
		for(var i in key) {
			var name = i;
			var value = key[i];
			rez.push(name + '=?');
			cut.push(value);
		}
		return rez.join(' AND ');
	},
	makeSelect: function(object, linked_where) {
		/*
		statement=
		{
			type:'select|update|insert|delete',
			fields:['field_name', select,...],
			from:'string',
			where:['string',...],
			link:['string',...],
			order:'string',
			group:'string'
		}
		*/
		/*
		Link патчит значениями и полями все свои '?', а также все вопросики в where ниже на один уровень запросах
		*/
		var sql = { type:'SELECT', fields:[], from:'' ,where:[], link: [] };
		//fields
		for(var i = 0; i < object.used.length; ++i) {
			var field = object.used[i];
			if(field.select) {
				var cond = field.elem.linked_where();
				sql.fields.push(this.makeSelect(field.select, this.linkedCondition(cond, sql.link)));
			} else {
				sql.fields.push(field.node.alias+"."+field.elem.$.name);
			}
		}
		//from
		if(X.isArray(object.joins)) 
			sql.from = object.joins.join('')
		else
			sql.from = object.joins;
		
		sql.link = sql.link.concat(object.links);
		
		//where
		linked_where && 
			sql.where.push(linked_where);
		
		return sql;
	},
	makeUpserte: function(object) {
		/*
		object = { 
					table: qe.keyObject.$.name, 
					key: qe.keyObject.DBKeyValue,
					oid: X.OID(qe.keyObject)
					values:{name:value, name:value}
				}
		*/
		var sql = { type:undefined, fields:undefined, from: object.table, where:undefined, link:[] };
		if(object.key) {
			sql.where = [this.keyCondition(object.key, sql.link)];
			if(!object.values) 
				sql.type = 'DELETE';
		}
		if(object.values) {
			sql.fields = [];
			for(var i in object.values) {
				var field = {};
				field[i] = '?';
				sql.fields.push(field);
				sql.link.push(object.values[i]);
			}
			sql.type = object.key ? 'UPDATE' : 'INSERT';
		}
		return sql;
	}
}
return rez;
})();