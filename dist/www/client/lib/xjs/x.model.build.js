﻿X.modelBuilder = (function(env) {
	var makeAlias = function(n) {
			return "abcderfghijklmnopqrstuvwxyz".charAt(n) ||
				makeAlias(n/10)+(n%10);
		}
	var res =
	{
		tableNode: function(tableObject, parent) {
			var self = this;
			self.$$ = tableObject;
			self.parent = function() { return parent }
			//self.parent = parent;
			self.alias = "";
			self.condition = null;
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
			
			self.ready = ko.observable(false);
			/*self.ready = ko.computed(
			{
				'read':function() {
					return self._ready();
				},
				'write':function(val) {
					self._ready(val);
					for(var i in self) {
						var rel = self[i];
						if(rel && rel.joins) {
							for(var j in rel.joins) { //it's rel params
								var node = rel.joins[j];
								node.ready(val);
							}
						}
					}
				}
			});
			*/
			self.destroyElement = function() {
				//elm.destroyElement = function(to_destroy) { 
				//	this.destroy(to_destroy);
				//	to_destroy.key._destroy = true;
				//	to_destroy.sendToServer();
				//}
				console.log(this);
				self.key._destroy = true;
				self.key.sendToServer(self);
				if(self.parent() && env.isMulti(self.parent())) {
					self.parent().remove(self);
				}
			}
			
			return this;
		},
		traverseRel: function() {
			var key = Array.prototype.join.call(arguments,":") || "";
			//table linked with rel //rel itself
			return this.joins[key] ||
				(this.joins[key] = new X.modelBuilder.tableNode(this.$$, this));
		},
		appendElement: function(cont, def) {
			var table_node = new X.modelBuilder.tableNode(def, cont);
			
			if(env.isMulti(cont))
				cont.push(table_node);
			else
				cont[def.name] = table_node;
			
			this.makeAliases(table_node);
			this.makeUpdatables(table_node);
			this.makeRels(table_node);
			this.makeWheresLinks(table_node);
			return table_node;
		},
		//x.rel() == X.modelBuilder.traverseRel(this.rel, arguments)
		makeCondition: function(root, cond, context, rel_params) {
			if(cond.cache && cond.cache[rel_params]) return cond.cache[rel_params]; //cached
			//context.rel root.id, root.field1, field1 = const or field1 = field2
			var real_expr = {where:"", link:[]};
			var where = [];
			for(var i=0;i<cond.length;++i) {
				var fld = root[cond[i].there];
				if(cond[i].value) {
					where.push(X.sql.node(fld)+'=?');
					real_expr.link.push(cond[i].value);
				}
				else
					where.push(X.sql.node(fld)+'='+X.sql.node(context[cond[i].here]));
			}
			//TODO: find appropriate table in context stack
					// it's changed from call to call so can't be cached - what we should do?
					//BUT!
					// we always has condition in from field1 = const or field1 = field2
					// where field1 is from right table and field2 from context table (which is one level up)
					// at least for now it's always so
					// so! we can just bound field2 to alias later, in sql
			params = rel_params && rel_params.split(':');
			if(params) {
				var p = 0;
				for(var i = 0; i < real_expr.link.length; ++i)
					if(real_expr.link[i] === '?')
						real_expr.link[i] = params[p++];
			}
			real_expr.where = where.join(' AND ');
			cond.cache = cond.cache || {};
			return cond.cache[rel_params] = real_expr;
		},
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
		makeRels: function(table_node) {
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) { //it's rel params
						var target_node = rel.joins[j];
						target_node.condition = 
							X.modelBuilder.makeCondition(target_node, rel.$.condition, table_node, j);
						this.makeRels(target_node);
					}
				}
			}
		},
		generateKeyObject: function(table_node) {
			if(table_node.key) return table_node.key;
			var kv = {}
			for(var i in table_node) {
				var elem = table_node[i];
				if(elem && elem.$ && elem.$.pk)
					kv[elem.$.name] = elem;
				if(elem && elem.value && elem.value.$.pk)
					kv[elem.value.$.name] = elem.value;
			}
			return table_node.key = X.Upserte.new_key(table_node.$$, kv);
			//TODO: subscibe to changes
		},
		makeUpdatables: function(table_node) {
			for(var i in table_node) {
				var elem = table_node[i];
				elem = elem && elem.value || elem;
				if(elem && env.boundAsUpdatable(elem)) {
					elem.key = this.generateKeyObject(table_node);
					elem.sendToServer = function() { this.key.sendToServer(this); }
					env.convertToUpdatable(elem);
				}
				
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins)
						this.makeUpdatables(rel.joins[j]);
				}
			}
		},
		makeWheresLinks:function(table_node){
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && env.isMulti(rel) && env.used(rel)) {
					rel.link = ko.computed(function() {
						var link = [];
						var conds = this.$.condition;
						for(var i=0;i<conds.length;++i) {
							var f = table_node[conds[i].here];
							var v = f();//subscribe to key link fields anyway (refresh need key)
							if(rel.defer)
								link.push(v)
							else
								link.push({field:X.sql.node(f)});
						}
						return link;
					}, rel);
				}
				else if(rel && rel.joins) {
					for(var j in rel.joins) 
						this.makeWheresLinks(rel.joins[j]);
				}
			}
		},
		collectUsage: function(table_node, used) {
			//here we have condition for this node
			//we should right(!) associate joins under relations
			// T1->T2->T3
			// T1 left join (T2 left join T3 on T3.rid = T2.rel) on T2.rid=T1.rel
			// it's returned as [T1:null [T2(T2.rid=T1.rel) T3(T3.rid = T2.rel)]]
			
			for(var i in table_node) {
				var elem = table_node[i];
				if(elem && env.isMulti(elem)) {
					if(elem.link && elem.auto) {
						var select = X.sql.makeSelect(elem, elem.$$);
						used.push({node: table_node, elem: elem, 
							select: select.sql });
						elem.remove(select.test_node);
					}
				}
				else
					if(elem && env.used(elem))
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
		}
	}
	return res;
})(X.DBdefaultEnv);