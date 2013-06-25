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
			//self.parent = function() { return container }
			//self.parent = parent;
			self.alias = "";
			self.condition = null;
			self.link = null;
			self.where = "";
			self.key = null;
			//this.ready = ko.observable(false);
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
			//container.parent - tableNode with self in
			self.destroy = function() {
				if(self.key.ready()) {
					self.key._destroy = true;
					self.key.sendToServer(self);
				}
				else {
					self.key.container.remove(self);
				}
			}
			//self.ready = ko.observable(false);
			X.modelBuilder.makeOps(container, self, rel_params);
			return this;
		},
		traverseRel: function() {
			var key = Array.prototype.join.call(arguments,":") || "";
			//table linked with rel //rel itself
			return this.joins[key] ||
				(this.joins[key] = new X.modelBuilder.tableNode(this.$$, this, key));
		},
		makeOperands:function(elm, current_node, new_node, rel_params) {
			var link = [];
			var p = 0;
			var params = rel_params && rel_params.split(':');
			for(var i=0;i<elm.$.condition.length;++i) {
				var c = elm.$.condition[i];
				var ops = { 
					field: new_node[env.isMulti(elm) ? c.point : c.target],//table_node - new node
					value: current_node[env.isMulti(elm) ? c.target : c.point]}//this.parent - current node);
				
				if(c.value) {//rawvalue
					ops.rawvalue = (c.value === "?" ? params[p++] : c.value);
					link.valuable = true;
				}
				env.oko(ops.value || ops.field).within[elm.$.name] = elm;//knowledge for fields of relations
				link.push(ops);
			}
			return link;
		},
		makeOps: function(cont, table_node, rel_params) {
			if(cont.$.condition)
				table_node.linkops = X.modelBuilder.makeOperands(cont, cont.parent, table_node, rel_params);
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) { //it's rel params
						this.makeOps(rel, rel.joins[j], j);
					}
				}
			}
		},
		appendElement: function(cont) {
			var table_node = new X.modelBuilder.tableNode(cont.$$, cont);
			
			if(env.isMulti(cont))
				cont.push(table_node);
			else
				cont = table_node;//TODO: develop this moment

			this.makeAliases(table_node);
			this.makeUpdatables(cont, table_node);
			this.makeRels(cont, table_node);
			this.makeRelKeys(table_node);
			return table_node;
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
		makeLink: function(container, table_node, rel_params) {
			table_node.link = ko.computed(function() {
				if(!this.$.condition) return [];
				var where = [];
				var link = [];
				var ops = table_node.linkops;
				//table_node - new node
				//this.parent - current node
				for(var i=0;i<ops.length;++i) {
					var o_fld = env.oko(ops[i].field)
					var o_val = ops[i].value ? env.oko(ops[i].value) : null
					var raw = ops[i].rawvalue
					var value = null;
					//value calculation and subscription
					if(env.isMulti(this)) {
						if(!o_val) 
							throw "Error: array is built on backrel with parametrised condition";
						value = env.read(o_val);//always subscribe
						//TODO:subscribe if updatables, other way - peek
					} else {
						value = o_val ? env.peek(o_val) : raw;//no subcription. those subscriptions in updatables
					}
					if( o_val && !o_val.sync() ) {
						//join and where differences
						if(env.isMulti(this)) {
							where.push( X.sql.node(o_fld) +"=?");
							link.push({ field: X.sql.node(o_val) });
						} else
							where.push( X.sql.node(o_fld) +"="+ X.sql.node(o_val));
					} else {
						//edited || sync()
						if(X.isEmpty(value))
							where.push( X.sql.node(o_fld) +" IS NULL");
						else {
							where.push( X.sql.node(o_fld) +"=?");
							link.push( value );
						}
					}
				}
				table_node.where = where.join(" AND ");
				return link;
			}, container);
		},
		generateRelKey:function(table_node) {
			if(table_node.relkey) return table_node.relkey;
			var kv = null;
			for(var i=0; i < table_node.linkops.length; ++i) {
				var v = table_node.linkops[i].value || table_node.linkops[i].field;
				if(v && env.boundAsUpdatable(env.oko(v))) {
					(kv = kv || {})[v.$.name] = v;
				}
			}
			return table_node.relkey = kv && X.Select.new_key(kv);
		},
		makeRelKeys: function(table_node) {
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) { //it's rel params
						var ops = rel.joins[j].linkops;
						for(var i=0;i<ops.length;++i)
							//if(ops[i].value)
								(ops[i].value || ops[i].field).relkey = this.generateRelKey(rel.joins[j]);
						this.makeRelKeys(rel.joins[j]);
					}
				}
			}
		},
		makeRels:function(container, table_node, rel_params) {
			this.makeLink(container, table_node, rel_params);
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) { //it's rel params
						this.makeRels(rel, rel.joins[j], j);
					}
				}
			}
		},
		generateKeyObject: function(cont, table_node) {
			if(table_node.key) return table_node.key;
			var kv = {}
			for(var i in table_node) {
				var elem = table_node[i] && env.oko(table_node[i]);
				if(elem && elem.$ && elem.$.pk)
					kv[elem.$.name] = elem;
			}
			return table_node.key = X.Upserte.new_key(cont, table_node, kv);
			//TODO: subscibe to changes
		},
		makeUpdatables: function(cont, table_node) {
			for(var i in table_node) {
				var elem = table_node[i] && env.oko(table_node[i]);
				if(elem && env.boundAsUpdatable(elem)) {
					elem.key = this.generateKeyObject(cont, table_node);
					elem.sendToServer = function() {
						this.key.sendToServer(this);
					}
					env.convertToUpdatable(elem);
				}
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins)
						this.makeUpdatables(rel, rel.joins[j]);
				}
			}
		},
		//TODO:collectHeaders
		collectUsage: function(table_node, used) {
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
					var sql = X.sql.makeSelect(test.node);
					used.push({node: table_node, elem: elem, select: sql });
					test_nodes.push(test);
				}
			}
			
			for(var i in table_node) {
				var elem = table_node[i];
				if(elem && env.used(elem) && !env.isMulti(elem))
					used.push({node: table_node, elem: elem});
				var rel = elem;
				if(rel && rel.joins) { //it's rel
					for(var j in rel)
						if(rel[j] && env.used(rel[j]))//value
							used.push({node: table_node, elem: rel[j]});
					for(var j in rel.joins)
						this.collectUsage(rel.joins[j], used);
				}
			}
			//removing test nodes
			for(var i=0;i<test_nodes.length;++i)
				test_nodes[i].parent.remove(test_nodes[i].node);
		}
	}
	return res;
})(X.DBdefaultEnv);