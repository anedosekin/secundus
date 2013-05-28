X.modelBuilder = (function(env) {
	var makeAlias = function(n) {
			return "abcderfghijklmnopqrstuvwxyz".charAt(n) ||
				makeAlias(n/10)+(n%10);
		}
	var res =
	{
		tableNode: function(tableObject, parent) {
			var self = this;
			self.$ = tableObject;
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
				(this.joins[key] = new X.modelBuilder.tableNode(this.$.target, this));
		},
		//x.rel() == X.modelBuilder.traverseRel(this.rel, arguments)
		makeCondition: function(root, cond, context, rel_params) {
			if(cond.cache && cond.cache[rel_params]) return cond.cache[rel_params]; //cached
			//context.rel root.id, root.field1, field1 = const or field1 = field2
			var real_expr = [];
			for(var i=0;i<cond.length;++i) {
				var expr = { there : eval("root."+cond[i].there), value: cond[i].value };
				if(cond[i].here) expr.here = eval("context."+cond[i].here)
				//if(context.alias === "") expr.value = ko.computed(function() {return expr.here()});//дополнительный запрос
				real_expr.push(expr);
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
				for(var i = 0; i < real_expr.length; ++i)
					if(real_expr[i].value === '?')
						real_expr[i].value = params[p++];
			}
			cond.cache = cond.cache || {};
			return cond.cache[rel_params] = real_expr;
		},
		makeRelsAndAliases: function(table_node, context_node, alias) {
			alias = alias || { current: 0};
			table_node.alias = makeAlias(alias.current++);
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) { //it's rel params
						var target_node = rel.joins[j];
						target_node.condition = 
							X.modelBuilder.makeCondition(target_node, rel.$.condition, table_node, j);
						this.makeRelsAndAliases(target_node, table_node, alias);
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
			return table_node.key = X.Upserte.new_key(table_node.$, kv);
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
		collectUsage: function(table_node, used) {
			//here we have condition for this node
			//we should right(!) associate joins under relations
			// T1->T2->T3
			// T1 left join (T2 left join T3 on T3.rid = T2.rel) on T2.rid=T1.rel
			// it's returned as [T1:null [T2(T2.rid=T1.rel) T3(T3.rid = T2.rel)]]
			
			for(var i in table_node) {
				var elem = table_node[i];
				if(elem && env.isMulti(elem)) {
					if(elem.auto && env.used(elem)) 
						used.push({node: table_node, elem: elem, select:elem.makeQuery()});
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
		},
		linkUsedConditions: function(table_node) {
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel) {
					if(env.used(rel) && env.isMulti(rel)) {
						rel.linked_where();
					}
					else if(rel.joins) {
						for(var j in rel.joins) 
							this.linkUsedConditions(rel.joins[j], table_node);
					}
				}
			}
		},
		collectSubselects: function(table_node, selects) {
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel) {
					if(rel.auto && env.used(rel)) {
						selects.push(rel.makeQuery());
					}
					else if(rel.joins) {
						for(var j in rel.joins) 
							this.collectSubselects(rel.joins[j], selects);
					}
				}
			}
		},
		collectSQL: function(table_node, context_node) {
			this.makeUpdatables(table_node);
			this.makeRelsAndAliases(table_node, context_node);
			this.linkUsedConditions(table_node);
			
			var used = [];
			this.collectUsage(table_node, used);
			var links = [];
			var joins = X.sql.collectJoins(table_node, links);
			var selects = [];
			this.collectSubselects(table_node, selects);
			return {
				used: used, //SELECT
				joins: joins, //FROM
				links: links, //values for questions
				selects: selects //subselects
			}
		}
	}
	return res;
})(X.DBdefaultEnv);