X.DBdefaultEnv = {
	//elm - observable
	//read from observable
	read: function(elm) { return elm.value ? elm.value() : elm(); },
	//write to observable or observableArray
	write:function(elm, value) {
		(elm.joins ? elm.value : elm)(value);
	},
	writeFromDB:function(elm, value) {
		elm.DBValue(value);
		this.write(elm, value);
	},
	writeRecord: function(elm, usage, value) {
		var i = 0;
		for(var j in value) {
			//TODO: server answer value length may be greater than usage. should check it and alert
			var data = value[j];
			var elem = usage[i].elem;
			if(this.isMulti(elem)) {
				this.writeArray(elem, data);
			} else {
				this.writeFromDB(elem, data);
			}
			i++;
		}
		elm.ready(true);
	},
	writeArray: function(elm, value) {
		for(var i = 0;i < value.length;++i) {
			var usage = [];
			var node = X.modelBuilder.appendElement(elm, elm.$$);
			X.modelBuilder.collectUsage(node, usage);
			this.writeRecord(node, usage, value[i]);
		}
		elm.ready(true);
	},
	isChanged:function(elm) {
		return this.read(elm) !== elm.DBValue();
	},
	//mark observable as it has error when data came from server
	//(usuccessfull try to write data)
	writeSaveError: function(elm, err) { alert('Save error:'+err); /*or set attribute!*/ },
	writeSelectError: function(elm, err) { alert('Select error:'+err); },
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
		c.DBValue = ko.observable();
	},
	isMulti:function(elm) {return elm.addNewLine },
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
			c.joins = {};
			c.$ = fielddef;
			c.$$ = fielddef.target;
			c.parent = container;
			X.DBdefaultEnv.makeElement(c, 'value', fielddef); //observable = rel value (as field value)
			//TODO:
			//1) array to choose rel (if editable!) (it's like subitems = our array, but without where)
			//2) text - observable to show rel in UI (may be same as val)
			//3) tip - observable to show rel's light details
			// and maybe something to our relation bind
	},
	makeArray: function(container, name, def) {
		//making subitems and main array - mix
		var env = this;
		var c = container[name] = ko.observableArray();
		c.$ = def;
		c.$$ = def.target || def;
		c.parent = container;
		c.auto = def.array && def.array.indexOf('auto')==0;
		c.defer = def.array && def.array.indexOf('defer')==0;
		c.ready = ko.observable(false);
		c.sendQuery = function() {
			c.ready(false);
			X.Select.sendQuery(c);
		}
		c.addNewLine = function() {
			X.modelBuilder.appendElement(c, def);
		}
	},
	makeRecord: function(def) {
		var env = this;
		var c = new X.modelBuilder.tableNode(def);
		c.$ = def;
		c.sendQuery = function() {
			c.ready(false);
			var sql = X.modelBuilder.collectSQL(c);
			var json = X.sql.makeSelect(sql);
			env.send(json, function(data) { env.writeRecord(c, sql.used, data) });
		}
		return c;
	},
	onSendError: X.log,
	url: "/server/lib/dbwork.php",
	send: function(data, onresponce, onerror) {
		var responseHandle = X.server.response.bind(this, onresponce);
		var p = X.XHR("POST", this.url, X.server.query(data),{"Content-Type":"application/json"})
				.then(responseHandle, onerror)
				.done();
	},
	interval: 300,
	timeout: 10*1000
}
X.server = (function(env) {
	return {
		response: function(onresponse, data) {
			/*
			{
				result:
				{
					commands:
					[
						{
							Original command,
							SUCCESS:true|false,
							ROWS:number,
							RESULTSET:[{"f1":"v1","f2":"v2"},{...},{...}],
							"MSGTXT":'error text',
							"SQLSTATE":'error number'
						}
					]
				},
				"errors":{
					"system":
					[
						{"MSGTXT":"Error content type!","SQLSTATE":-1}
					]
				}
			}
			*/
			var re = /\{"result":.*$/g;
			if(answer = data.match(re)) {
				var warning;
				if(warning = data.replace(re,"").replace(/\s+$/g,"")) 
					console.log(warning);
				var ansObj = JSON.parse(answer[0]);
				if(ansObj.errors) 
					throw ansObj.errors;
				onresponse(ansObj.result.commands);
			} else {
				console.log(data);
			}
		},
		query: function(data) {
			var to_send = { seed: X.cid.seed(), commands:[] };
			if(X.isArray(data)) {
				for(var i = 0;i < data.length;++i) to_send.commands.push(data[i]);
			}
			else {
				to_send.commands.push(data);
			}
			return JSON.stringify(to_send);
		}
	}
})(X.DBdefaultEnv);