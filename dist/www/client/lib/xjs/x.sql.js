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
					ret.push(' LEFT OUTER JOIN ');
					ret.push(this.collectJoins(rel.joins[j], cut_link));
					ret.push(' ON '+this.joinCondition(rel.joins[j].condition, cut_link));
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
		ret.unshift(table_id(table_node)); // if we have joins, add table to first element in join sequence
		return '('+ret.join('')+')';
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
	linkedCondition:function(conds, link) {
		var rez = [];
		for(var i=0;i<conds.length;++i) {
			var cond = conds[i];
			link.push(cond.value ? cond.value : this.nodeToString(cond.here));
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
			TYPE:'SELECT|UPDATE|INSERT|DELETE',
			FIELDS:['field_name', select,...],
			FROM:'string',
			WHERE:['string',...],
			LINK:['string',...],
			ORDER:'string',
			GROUP:'string'
		}
		*/
		/*
		Link патчит значениями и полями все свои '?', а также все вопросики в where ниже на один уровень запросах
		*/
		var sql = { TYPE:'SELECT', FIELDS:[], FROM:'' ,WHERE:[], LINK: [] };
		//fields
		for(var i = 0; i < object.used.length; ++i) {
			var field = object.used[i];
			if(field.select) {
				var cond = field.elem.linked_where();
				sql.FIELDS.push(this.makeSelect(field.select, this.linkedCondition(cond, sql.link)));
			} else {
				sql.FIELDS.push(field.node.alias+"."+field.elem.$.name);
			}
		}
		//from
		if(X.isArray(object.joins)) 
			sql.FROM = object.joins.join('')
		else
			sql.FROM = object.joins;
		
		sql.LINK = sql.LINK.concat(object.links);
		
		//where
		linked_where && 
			sql.WHERE.push(linked_where);
		/*for(var i in sql) {
			if(X.isArray(sql[i]) && sql[i].length==0) sql[i] = undefined;
			else
			if(!sql[i]) sql[i] = undefined;
		}*/
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
		var sql = { oid: object.oid, TYPE:undefined, FIELDS:undefined, FROM: object.table + ' a', WHERE:undefined, LINK:[] };
		if(object.values) {
			sql.FIELDS = [];
			for(var i in object.values) {
				var field = {};
				field[i] = '?';
				sql.FIELDS.push(field);
				sql.LINK.push(object.values[i]);
			}
			sql.TYPE = object.key ? 'UPDATE' : 'INSERT';
		}
		if(object.key) {
			sql.WHERE = [this.keyCondition(object.key, sql.LINK)];
			if(!object.values) 
				sql.TYPE = 'DELETE';
		}
		console.log(JSON.stringify(sql));
		return sql;
	}
}
return rez;
})();