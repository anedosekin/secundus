X.DBdefaultEnv = {
	//elm - observable
	//read from observable
	read: function(elm) { return elm.value ? elm.value() : elm(); },
	//write to observable or observableArray
	write:function(elm, value, remote) {
		if(remote) elm.DBValue = value;
		(elm.joins ? elm.value : elm)(value);
	},
	writeRecord: function(elm, usage, value, remote) {
		var i = 0;
		for(var j in value) {
			//TODO: server answer value length may be greater than usage. should check it and alert
			var data = value[j];
			var elem = usage[i].elem;
			if(this.isMulti(elem)) {
				this.writeArray(elem, data, remote);
			} else {
				this.write(elem, data, remote);
			}
			i++;
		}
		elm.ready(true);
	},
	writeArray: function(elm, value, remote) {
		for(var i = 0;i < value.length;++i) {
			var usage = [];
			var node = elm.appendElement();
			X.modelBuilder.makeUpdatables(node);
			X.modelBuilder.linkUsedConditions(node);
			X.modelBuilder.collectUsage(node, usage);
			this.writeRecord(node, usage, value[i], remote);
		}
		elm.ready(true);
	},
	isChanged:function(elm) {
		return this.read(elm) !== elm.DBValue;
	},
	//mark observable as it has error when data came from server
	//(usuccessfull try to write data)
	writeSaveError: function(elm, err) { alert(err); /*or set attribute!*/ },
	//check if this observable bound to something (need to be read)
	used: function(elm) {
		return elm.$ && elm.getSubscriptionsCount && elm.getSubscriptionsCount(); 
	},
	//check if this observable bound to input or can be changed somehow else
	boundAsUpdatable: function(elm) { return elm.boundAsUpdatable; },
	//make observable with name in container using fielddef as description
	makeElement: function(container, name, fielddef) {
		var c = container[name] = ko.observable();
		c.$ = fielddef;
		c.parent = container;
	},
	isMulti:function(elm) {return elm.appendElement },
	//convert element (made with makeElement) to writable one (which is able to send itself to server)
	convertToUpdatable: function(elm) {
		elm.subscribe(function() {
			elm.sendToServer();
		});
	},
	//make relation, eq rel() = X.modelBuilder.traverseRel
	// but with additionals members
	// like joins{}, val, and so on
	makeRelation: function(container, name, fielddef) {
			var c = container[name] = function() { return X.modelBuilder.traverseRel.apply(this[name], arguments); }
			c.joins = {}
			c.$ = fielddef;
			c.parent = container;
			X.DBdefaultEnv.makeElement(c, 'value', fielddef); //observable = rel value (as field value)
			//TODO:
			//1) array to choose rel (if editable!) (it's like subitems = our array, but without where)
			//2) text - observable to show rel in UI (may be same as val)
			//3) tip - observable to show rel's light details
			// and maybe something to our relation bind
	},
	makeArray: function(container, name, def) {
		var env = this;
		var c = container[name] = ko.observableArray();
		console.log(def.name+' is used:'+env.used(c));
		c.$ = def;
		c.parent = container;
		c.auto = def.array && def.array.indexOf('auto')==0;
		c.defer = def.array && def.array.indexOf('defer')==0;
		c.ready = ko.observable(false);
		c.current_node = ko.observable();
		c.external = ko.computed(function() {
			if(!def.condition) return;
			var table_node = c.current_node();
			var rez = [];
			for(var i=0;i<def.condition.length;++i) {
				var cond = def.condition[i];
				var expr = {there: (table_node || {})[cond.there], value: cond.value, here: cond.here};
				if(c.defer && cond.here) { 
					expr.value = c.parent[cond.here]();
				}
				rez.push(expr);
			}
			return rez;
		}, c, {deferEvaluation: true});/*считается только для используемых массивов*/
		c.appendElement = function() {
			c.current_node(new X.modelBuilder.tableNode(def.target || def, c));
			var table_node = c.current_node();
			table_node.external = c.external();
			c.push(table_node);
			return table_node;
		}
		c.makeQuery = function() {
			var init_node = c.appendElement();
			var sql = X.modelBuilder.makeSQL(init_node, c.parent);
			c.remove(init_node);
			return sql;
		}
		c.sendQuery = function() {
			c.ready(false);
			if(c().length) c.removeAll();
			var json = X.modelBuilder.sqlToJSON(c.makeQuery());
			env.send(json, function(data) { env.writeArray(c, data, true) });
		}
	},
	makeRecord: function(def) {
		var env = this;
		var c = new X.modelBuilder.tableNode(def);
		c.$ = def;
		c.sendQuery = function() {
			c.ready(false);
			var sql = X.modelBuilder.makeSQL(c);
			var json = X.modelBuilder.sqlToJSON(sql);
			env.send(json, function(data) { env.writeRecord(c, sql.used, data, true) });
		}
		return c;
	},
	onSendError: X.log,
	url: "/save",
	send: function(data, onresponce, onerror) {
		
		var p = X.XHR("POST", this.url, data)
				.then(onresponce, onerror)
				.done();
		
		/*
		var seen = [];
		document.getElementById('json').innerHTML = JSON.stringify(data,
			function(key, val) {return val});
		document.getElementById('jsonpaste').innerHTML = JSON.stringify(data,
			function(key, val) {return val}, 2);
		onresponce(SQLTODATA(data));
		*/
	},
	interval: 300,
	timeout: 10*1000
}