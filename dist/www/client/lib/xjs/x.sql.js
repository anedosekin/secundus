X.sql = (function() {
var rez = {
	node:function(elm) {
		if(!elm) return 'error';
		return elm.node ? (elm.node.alias+'.'+elm.$.name) : elm;
	},
	table_id: function (node) {
		return node.$$.name + ' ' + node.alias;
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
					var conds = rel.filter.gerenal( j );
					ret.push(' LEFT OUTER JOIN ');
					ret.push(this.collectJoins(rel.joins[j], link));
					ret.push(' ON '+conds.where);
					for(var k=0;k<conds.link.length;++k)
						link.push(this.prepareLink(conds.link[k]));
				}
			}
		}
		
		
		if(ret.length == 0) {
			//no subjoins -> return table as it is
			return this.table_id(table_node);
		}
		ret.unshift(this.table_id(table_node)); // if we have joins, add table to first element in join sequence
		return '('+ret.join('')+')';
	},
	/*
		All quotes supplied in values
	*/
	keyCondition: function(key, link) {
		var where = [];
		for(var i in key) {
			var name = i;
			var value = key[i];
			where.push(name + '=?');
			link.push(this.prepareLink(value));
		}
		return where.join(' AND ');
	},
	prepareLink:function(link) {
		if(X.isObject(link)) {
			if(link.field)
				return { DATA: link, INSEL: true }
		} else
			return { DATA: link}
	},
	makeSelect: function(table_node, filter, oid) {
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
		if(oid) 
			sql.oid = oid;
		//fields
		var fields = [];
		var subselects = [];
		var s = 0;
		X.modelBuilder.collectUsage(table_node, fields, subselects);
		for(var i = 0; i < fields.length; ++i) {
			if(fields[i].append) {
				sql.FIELDS.push(subselects[ s++ ]);
			} else {
				sql.FIELDS.push(fields[i].node.alias+"."+fields[i].$.name);
			}
		}
		//from
		sql.FROM = X.sql.collectJoins(table_node, sql.LINK);
		//where
		sql.WHERE = [];
		if(filter) {
			sql.WHERE.push( filter.where );
			for(var i=0;i<filter.link.length;++i) {
				sql.LINK.push(this.prepareLink(filter.link[i]));
			}
		}
		return sql.FIELDS.length && { sql: sql, dist: fields };
	},
	addField: function( sql, name, val ) {
		var field = {};
		field[name] = "?";
		sql.FIELDS.unshift( field );
		if(X.isEmpty( val ))
			sql.LINK.unshift(this.prepareLink( null ));
		else
			sql.LINK.unshift(this.prepareLink( val ));
		
	},
	makeUpserte: function( key ) {
		/*
		object = { 
					table: qe.keyObject.$.name, 
					key: qe.keyObject,
					oid: X.OID(qe.keyObject)
					values:{name:value, name:value}
				}
		*/
		var sql = { oid: X.OID(key), TYPE:'INSERT', FIELDS:[], FROM: key.table_name +' a', WHERE:undefined, LINK:[] };
		if(key.values) {
			sql.TYPE = key._destroy ? 'DELETE' : 'UPDATE';
			sql.WHERE = [ key.values.where ];
			for(var i=0;i<key.values.link.length;++i) {
				sql.LINK.push(this.prepareLink(key.values.link[i]));
			}
		}
		return sql;
	},
	makeUpserte2: function(key, values, oid) {
		/*
		object = { 
					table: qe.keyObject.$.name, 
					key: qe.keyObject,
					oid: X.OID(qe.keyObject)
					values:{name:value, name:value}
				}
		*/
		var sql = { oid: oid, TYPE:'INSERT', FIELDS:undefined, FROM: key.table_name +' a', WHERE:undefined, LINK:[] };
		if(values) {
			sql.FIELDS = [];
			for(var i in values) {
				var field = {};
				field[i] = "?";
				sql.FIELDS.push( field );
				if(X.isEmpty( values[i] ))
					sql.LINK.push(this.prepareLink( null ));
				else
					sql.LINK.push(this.prepareLink( values[i] ));
			}
		}
		if(key.values) {
			sql.TYPE = values ? 'UPDATE' : 'DELETE';
			sql.WHERE = [ key.values.where ];
			for(var i=0;i<key.values.link.length;++i) {
				sql.LINK.push(this.prepareLink(key.values.link[i]));
			}
		}
		return sql;
	},
	valuesFromUpdate: function(com) {
		if(!com.FIELDS) return;
		var vals = {};
		for(var i=0;i<com.FIELDS.length;++i) {
			var fld = com.FIELDS[i];
			for(var name in fld) {
				vals[name] = com.LINK[i].DATA;
			}
		}
		return vals;
	}
}
return rez;
})();