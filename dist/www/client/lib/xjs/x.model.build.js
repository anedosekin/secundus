X.modelBuilder = (function(env) {
	var makeAlias = function(n) {
			return "abcderfghijklmnopqrstuvwxyz".charAt(n) ||
				makeAlias(n/10)+(n%10);
		}
	var res =
	{
		tableNode: function(tableObject, container, rel_params) {
			var self = this;
			self.$$ = tableObject;
			self.alias = "";
			self.condition = null;
			self.link = null;
			self.where = "";
			self.key = null;
			self.rel_params = rel_params;
			self.element = function() { return container }
			for(var i in tableObject) {
				var fieldObject = tableObject[i];
				if(fieldObject.name) {
					if(fieldObject.target) 
						env.makeRelation(self, i, fieldObject);
					else
						env.makeElement(self, i, fieldObject);
				}
			}
			for(var i in tableObject) {
			//после заведения всех полей, потому что имена в кондишенах 
			//патчатся на ko объекты
				var fieldObject = tableObject[i];
				if(fieldObject.name) {
					if(fieldObject.array) 
						env.makeArray(self, i, fieldObject);
				}
			}
			//container - backrel ko.array or relation with value
			//container.node - tableNode with self in
			self.refresh = function() {
				var s = X.sql.makeSelect( self, self.key.current() );
				s && X.Q.async.send( s.sql, s.dist );
			}
			self.destroy = function() {
				function on_delete() {
					var c = self.element()
					if( env.isMulti(c) ) 
						c.remove(self);
				}
				if(self.key.ready()) {
					self.key._destroy = true;
					var d = X.sql.makeUpserte( self.key );
					d && X.Q.sync.send( d, on_delete );
				}
				else 
					on_delete()
			}
			return this;
		},
		traverseRel: function() {
			var key = Array.prototype.join.call(arguments,":") || "";
			//table linked with rel //rel itself
			return this.joins[key] ||
				(this.joins[key] = new X.modelBuilder.tableNode(this.$$, this, key));
		},
		appendElement: function(cont) {
			var table_node = new X.modelBuilder.tableNode(cont.$$, cont);
			
			if(env.isMulti(cont))
				cont.push(table_node);
			else
				cont = table_node;//TODO: develop this moment

			this.markupForUpdates( cont, table_node );
			
			return table_node;
		},
		markupForUpdates: function( cont, table_node ) {
			this.makeAliases(table_node);
			this.makeRelFilters( cont, table_node );
			this.makeKeys( cont, table_node );
		},
		//x.rel() == X.modelBuilder.traverseRel(this.rel, arguments)
		makeAliases:function(table_node, alias) {
			alias = alias || { current: 0};
			table_node.alias = makeAlias(alias.current++);
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) //it's rel params
						this.makeAliases(rel.joins[j], alias);
				}
			}
		},
		makeRelFilters:function(cont, table_node) {
			cont.filter = cont.filter || X.table_key.new_filter( cont, table_node );
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) { //it's rel params
						this.makeRelFilters(rel, rel.joins[j]);
					}
				}
			}
		},
		addKey: function(cont, table_node, elem) {
			elem.key = this.generateKey(cont, table_node);
		},
		generateKey: function(cont, table_node) {
			return table_node.key || (table_node.key = X.table_key.new_key(cont, table_node))
		},
		patchUpdatable:function(cont, table_node, elem) {
			this.addKey(cont, table_node, elem)
			if(!elem.sendToServer) {
				elem.sendToServer = function() {
					this.key.sendToServer(this);
				}
				env.convertToUpdatable( elem );
			}
		},
		makeKeys: function(cont, table_node) {
			//making keys for updatables, preparing updatables
			if( env.selectableNode( table_node ))
				this.generateKey(cont, table_node)
			for(var i in table_node) {
				var elem = table_node[i] && env.oko( table_node[i] );
				if(elem && env.isField(elem) ) {
					if( env.generateAllKeys )
						this.addKey(cont, table_node, elem)
					if( env.boundAsUpdatable(elem) )
						this.patchUpdatable(cont, table_node, elem)
				}
				
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins)
						this.makeKeys(rel, rel.joins[j]);
				}
			}
			for(var i in table_node) {
				var rel = table_node[i];
				if( rel && env.isRel(rel) && env.boundAsUpdatable(rel) ) {
					//valued relations hasnt got value field
					X.table_key.auto_requery( rel.filter );
					var flds = rel.filter.fields;
					for(var i in flds)
						if(flds[i].value)
							this.patchUpdatable(cont, table_node, env.oko( flds[i].value ))
					for(var j in rel.joins)
						for(var k in flds)
							if(!flds[k].value) {//updatable field of target_node
								var fld = rel.joins[j][ flds[k].field.name ]
								this.patchUpdatable(cont, rel.joins[j], env.oko( fld ) )
							}
				}
			}
		},
		//TODO:collectHeaders
		collectUsage: function(table_node, used, subselects) {
			//here we have condition for this node
			//we should right(!) associate joins under relations
			// T1->T2->T3
			// T1 left join (T2 left join T3 on T3.rid = T2.rel) on T2.rid=T1.rel
			// it's returned as [T1:null [T2(T2.rid=T1.rel) T3(T3.rid = T2.rel)]]
			var test_nodes = [];
			for(var i in table_node) {
				var elem = table_node[i];
				if(elem && elem.auto && env.used(elem) ) {
					var test = { parent: elem, node: X.modelBuilder.appendElement(elem) };
					subselects && subselects.push( X.sql.makeSelect( test.node, elem.filter.gerenal() ).sql );
					used.push( elem );
					test_nodes.push(test);
				}
			}
			
			for(var i in table_node) {
				var elem = table_node[i];
				if(elem && env.used(elem) && !env.isMulti(elem))
					used.push( elem );
				var rel = elem;
				if(rel && rel.joins) { //it's rel
					if(env.used( rel._value ))
						used.push( rel._value );
					for(var j in rel.joins)
						this.collectUsage(rel.joins[j], used, subselects);
				}
			}
			//removing test nodes
			for(var i=0;i<test_nodes.length;++i)
				test_nodes[i].parent.remove(test_nodes[i].node);
		}
	}
	return res;
})(X.DBdefaultEnv);