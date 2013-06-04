X.sql = (function() {
var rez = {
	node:function(node) {
		if(!node) return 'error';
		return node.parent ? (node.parent.alias+'.'+node.$.name) : node;
	},
	collectJoins: function(table_node, link) {
		// T1->T2->T3
		// T1 left join (T2 left join T3 on T3.rid = T2.rel) on T2.rid=T1.rel
		// it's returned as [T1, [T2, T3]]
		var ret = [];
		for(var i in table_node) {
			var rel = table_node[i];
			if(rel && rel.joins) { //it's rel
				for(var j in rel.joins) { //it's rel params
					var on = rel.joins[j].condition;
					ret.push(' LEFT OUTER JOIN ');
					ret.push(this.collectJoins(rel.joins[j], link));
					ret.push(' ON '+on.where);
					for(var k=0;k<on.link.length;++k)
						link.push(on.link[k]);
				}
			}
		}
		function table_id(node) {
			return node.$$.name + ' ' + node.alias;
		}
		
		if(ret.length == 0) {
			//no subjoins -> return table as it is
			return table_id(table_node);
		}
		ret.unshift(table_id(table_node)); // if we have joins, add table to first element in join sequence
		return '('+ret.join('')+')';
	},
	makeWhere: function(cont, table_node, links) {
		if(!cont.link) return [];
		var where = [];
		var c = cont.$.condition
		for(var i=0;i<c.length;++i) {
			where.push(X.sql.node(table_node[c[i].there])+'=?');
		}
		var link = cont.link();
		for(var i=0;i<link.length;++i) {
			links.push(link[i]);
		}
		return [where.join(' AND ')];
	},
	/*
		All quotes supplied in values
	*/
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
	makeSelect: function(cont, def) {
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
		//link патчит значениями и полями все '?' в своём запросе

		var sql = { TYPE:'SELECT', FIELDS:[], FROM:'' ,WHERE:[], LINK: [] };
		
		var new_node = X.modelBuilder.appendElement(cont, def);
		
		//fields
		var fields = [];
		X.modelBuilder.collectUsage(new_node, fields);
		for(var i = 0; i < fields.length; ++i) {
			if(fields[i].select) {
				sql.FIELDS.push(fields[i].select);
			} else {
				sql.FIELDS.push(fields[i].node.alias+"."+fields[i].elem.$.name);
			}
		}
		//from
		sql.FROM = X.sql.collectJoins(new_node, sql.LINK);
		//where
		sql.WHERE = X.sql.makeWhere(cont, new_node, sql.LINK);
		
		return {sql: sql, test_node: new_node};
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
	},
	valuesFromUpdate: function(com) {
		var vals = {};
		for(var i=0;i<com.FIELDS.length;++i) {
			var fld = com.FIELDS[i];
			for(var name in fld) {
				vals[name] = com.LINK[i];
			}
		}
		return vals;
	}
}
return rez;
})();