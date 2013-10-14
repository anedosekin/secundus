X.DBdefaultEnv = {
	//elm - observable
	//read from observable
	oko:function(elm) { return elm.joins ? elm._value : elm },
	peek: function(elm) { return elm.peek() },
	read: function(elm) { return elm() },
	//write to observable or observableArray
	write: function(elm, value) {
		value = X.isEmpty(value) ? "" : value;
		elm(value);
	},
	isChanged:function(elm) {//doesnt generate any subscriptions
		return this.peek(elm) !== this.peek(elm.dbvalue).value;
	},
	//mark observable as it has error when data came from server
	//(usuccessfull try to write data)
	writeSaveError: function(elm, err) { alert('Update/Insert error:'+err); /*or set attribute!*/ },
	writeSelectError: function(elm, err) { alert('Select error:'+err); },
	//check if this observable bound to something (need to be read)
	used: function(elm) {
		return elm.$ && elm.getSubscriptionsCount && elm.getSubscriptionsCount(); 
	},
	//check if this observable bound to input or can be changed somehow else
	boundAsUpdatable: function(elm) { 
		return elm.boundAsUpdatable
	},
	selectableNode: function(node) {
		return node.selectableNode
	},
	makeUse:function(elm) {
		ko.utils.unwrapObservable(elm);
	},
	//make observable with name in container using fielddef as description
	makeElement: function(container, name, fielddef) {
		var env = this;
		var c = ko.observable("");
		if(fielddef.extend)
			for(var i=0;i<fielddef.extend.length;++i)
				c = c.extend(fielddef.extend[i]);
		container[name] = c;
		c.$ = fielddef;
		c.node = container.joins ? container.node : container;
		c.root = container.root || container.element && container.element().root || c;
		c.dbvalue = ko.observable({ value: "" });
		c.get_name = function() {//sql-I
			return this.$.name
		}
		c.write = function( value ) {//sql-I
			value = X.isEmpty(value) ? "" : value;
			env.write( this.dbvalue, { value: value, update: false } )
			env.write( this, value );
		}
		c.update = function(value) {//sql-I
			value = X.isEmpty(value) ? "" : value;
			env.write( this.dbvalue, { value: value, update: true } )
			env.write( this, value );
		}
		c.update_back = function() {//sql-I
			env.write( this, env.read( this.dbvalue).value );
		}
		c.update_similar = function( value ) {//sql-I
			env.updateSimilar(this, value);
		}
	},
	isField:function(elm) {return elm.$},
	isNode:function(elm) { return elm.element },
	isRel:function(elm) { return elm.joins },
	isMulti:function(elm) {return elm.append },
	//convert element (made with makeElement) to writable one (which is able to send itself to server)
	convertToUpdatable: function( elm ) {
		elm.subscribe(function() {
			if(this.isChanged(elm)) {
				elm.sendToServer();
			}
		}, this);
	},
	//make relation, eq rel() = X.modelBuilder.traverseRel
	// but with additionals members
	// like joins{}, val, and so on
	makeRelation: function(container, name, fielddef) {
			var c = container[name] = function() { return X.modelBuilder.traverseRel.apply(this[name], arguments); }
			c.joins = {};
			c.$ = fielddef;
			c.$$ = fielddef.target;
			c.node = container;
			c.root = container.root || container.element && container.element().root || c;
			c.filter = null;
			X.DBdefaultEnv.makeElement(c, '_value', fielddef); //observable = rel value (as field value)
			/*c.get_filters = function() {
				var f = [];
				for(var i in c.joins)
					f.push(c.joins[i].filter)
				return f;
			}*/
			/*c.refresh = function() {
				for(var i in c.joins) {
					var sql = X.sql.makeSelect(c.joins[i]);
					X.Select.sendSelect(c.joins[i], sql);
				}
			}*/
			//TODO:
			//1) array to choose rel (if editable!) (it's like subitems = our array, but without where)
			//2) text - observable to show rel in UI (may be same as val)
			//3) tip - observable to show rel's light details
			// and maybe something to our relation bind
	},
	makeArray: function(container, name, def) {
		//making subitems and main array - mix
		var c = container[name] = ko.observableArray();
		c.$ = def;
		c.$$ = def.target || def;
		c.node = container;
		c.root = container.root || container.element && container.element().root || c;
		c.auto = def.array && def.array.indexOf('auto')==0;
		c.defer = def.array && def.array.indexOf('defer')==0;
		c.filter = null;
		c.refresh = function() {
			c.sendSelect( c.filter.current() );
		}
		c.sendSelect = function( filter ) {
			var node = X.modelBuilder.appendElement( c );
			var s = X.sql.makeSelect( node, filter );
			s && X.Q.async.send( s.sql, c )
			c.remove( node );
		}
		c.append = function() {//sql-I
			var fields = [];
			var node = X.modelBuilder.appendElement( c );
			X.modelBuilder.collectUsage(node, fields);
			return fields;
		}
		c.before_fill = function() {//sql-I
			c.removeAll();
		}
	},
	updateSimilar: function( c_elm, value ) {
		if(c_elm.key && c_elm.key.values) {
			var env = this;
			function compare( elm ) {
				if( elm.key && elm.key.values) {
					if(	elm.node.$$.name == c_elm.node.$$.name &&
						elm.$.name == c_elm.$.name &&
						elm.key.values.hash == c_elm.key.values.hash) 
					{
						elm.update( value );
					}
				}
			}
			function trace_node(node) {
				for(var i in node) {
					node[i] && env.isField(node[i]) && trace_elm( node[i] );
				}
			}
			function trace_elm(elm) {
				if( env.isMulti(elm) ) {
					for(var i=0;i<elm().length;++i)
						trace_node(elm()[i])
				} else
				if( env.isRel(elm) ) {
					for(var i in elm.joins) 
						trace_node(elm.joins[i])
					compare( env.oko( elm ) );
				} else
				if( env.isField(elm) )
					compare( elm );
			}
			trace_elm( c_elm.root );
		}
	},
	makeRecord: function(def) {
		var c = new X.modelBuilder.tableNode(def);
		c.$ = def;
		/*c.sendSelect = function() {
			//c.ready(false);
			var sql = X.modelBuilder.collectSQL(c);
			var json = X.sql.makeSelect(sql);
			env.send(json, function(data) { env.writeRecord(c, sql.used, data) });
		}*/
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
	interval: 1000,
	timeout: 10*1000,
	generateAllKeys: true,
	logPhpMessage: false
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
				if(env.logPhpMessage)
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
			console.log(JSON.stringify(to_send));
			return JSON.stringify(to_send);
		}
	}
})(X.DBdefaultEnv);
X.utils = (function(env) {
	
})(X.DBdefaultEnv)