X.Queue = function(_send, _prepareCommand, _new_command,_prepareSending, _onresponse, _onerror, throttle) {
	function newQueue() { return { cmds: [] } }
	var DBQueue = newQueue();
	var sendingLock = 0;
	var cmdsWhenLocked = 0;
	var errorCount = 0;
	function onerror(sent, err) {
		//log...
		_onerror(err);
		++errorCount;
		//resend... 
		//DBQueue.cmds = sentDBQueue.cmds.concat(DBQueue.cmds);
		//sentDBQueue = null;
		//processQueue();
	}
	function onresponce(sent, cmds) {
		errorCount = 0;
		var objs = sent.cmds;
		for(var i = 0; i < cmds.length; ++i)
			_onresponse(cmds[i], objs[i]);//sever, client
	}
	function _processQueue() {
		if(sendingLock) { ++cmdsWhenLocked; return; }

		if(DBQueue.cmds.length === 0) return;
		
		var to_send_queue = [];
		
		for(i = 0; i < DBQueue.cmds.length; ++i)
			_prepareCommand(DBQueue.cmds[i], to_send_queue);
		
		if(to_send_queue.length) {
			var sentDBQueue = DBQueue;
			DBQueue = newQueue();
			_send(to_send_queue, onresponce.bind(this, sentDBQueue), onerror.bind(this, sentDBQueue))
		}
	}
	var processQueue = X.throttle(_processQueue, throttle);
	return {
		lockSending : function() { ++sendingLock; },
		unlockSending : function() { 
			if(--sendingLock == 0 && cmdsWhenLocked) {
				processQueue(); 
				cmdsWhenLocked = 0;
			} 
		},
		withLockedSending : function(f) { 
			try { this.lockSending(); return f(); } 
			finally { this.unlockSending(); } 
		},
		sendToServer : function(elm) {
			var nc = _new_command( { keyObject: elm } );
			var last = DBQueue.cmds.slice(-1)[0];
			var qe = last && nc.keyObject === last.keyObject ? last : nc;
			if(qe !== last)
				DBQueue.cmds.push(qe);
			
			_prepareSending(elm, qe);
			
			processQueue();
		}
	}
}
X.Upserte = (function(env) {
	function new_command(cmd) {
		cmd.keyObject = cmd.keyObject.key;
		cmd.objects = {}
		return cmd;
	}
	function prepareSending(elm, cont) {
		if(elm.key._destroy)
			cont.objects = null;
		
		if(cont.objects) { //if not destroyed!
			cont.objects[elm.$.name] = elm;
		}
		return cont;
	}
	function prepareUpserte(c, queue) {
		var to_send = { 
				table: c.keyObject.node.$$.name, 
				key: c.keyObject.DBKeyValue,
				oid: X.OID(c.keyObject) 
				}
		if(c.objects) { //update/insert
			to_send.values = {}
			for(var j in c.objects) {
				// due to toJSON isCid translated to {cid:val} which is enough
				to_send.values[j] = env.read(c.objects[j]);
			}
			queue.push(X.sql.makeUpserte(to_send));
		} else { //delete
			if(c.keyObject.DBKeyValue) //!DBKeyValue - new
				queue.push(X.sql.makeUpserte(to_send));
		}
	}
	function onerror(err) {
		env.onSendError("server responce:", err);
	}
	function onresponse(ser, cli) {//ser - server command, cli - client sent command
		var key = cli.keyObject;
		if(ser.oid !== X.OID(key)) {
			//TODO: check match!
			alert('oid is not match');
		}
		 //must match previous!
		if(ser.SUCCESS) {
			var values = X.sql.valuesFromUpdate(ser);
			if(values) {
				//insert/update
				for(var i in values) {
					var obj = cli.objects[i];
					if(obj) {
						obj = env.oko(obj);
						obj.dbvalue(values[i]); //from server!!!! cids translated there!!!
						if(env.isChanged(obj))
							env.write(obj, obj.dbvalue()); //if server change value
						obj.sync( true );
					} else {
						//server send new value, but does't ask it to change
					}
				}
				//recalculate key for whole row
				//DBKeyValue has db values
				var k = {};
				for(var i in key.values) //loop in keyObject
					k[key.values[i].$.name] = key.values[i].dbvalue(); //new DBValue here!
				key.DBKeyValue = k;
			} else {
				//delete
				key.container.remove(key.node);
				key.DBKeyValue = null; //clear key value from deleted records
			}
		} else {
			env.writeSaveError(key, JSON.stringify(ser));
		}
	}
	var queue = X.Queue(env.send.bind(env), 
					prepareUpserte, 
					new_command, 
					prepareSending, 
					onresponse, 
					onerror, 
					env.interval)
	return {
		toServer: queue.sendToServer,
		lockSending: queue.lockSending,
		unlockSending: queue.unlockSending,
		key: function(cont, table_node, vals) { // получает { value: KO }
			this.node = table_node;
			this.container = cont;
			//this.DBValue = undefined; //TODO: calc it when we read object from server, and set ready = true
			this.values = vals; //to make DBKeyValue and to trace changes in key 
			this.pended_changes = {}; // objects; pended_changes === null when ready
			this.ready = ko.computed(function() {
				var k = {};
				var insert_key = true;
				var ready = true;
				for(var i in this.values) {//TODO:simplify double reading
					ready &= !X.isEmpty(env.read(this.values[i]));//subscription to all fields
				}
				if(!ready) return false;
				for(var i in this.values) {
					var v = env.read(this.values[i]);
					if(this.values[i].$.pk) {
						v = this.values[i].dbvalue();
						if(!X.isEmpty(v)) //if all dbvalues of key is empty, then insert
							insert_key = false;
					}
					k[this.values[i].$.name] = v;
				}
				this.DBKeyValue = insert_key ? null : k;
				X.Upserte.lockSending();
				if(this.pended_changes)
					for(var i in this.pended_changes)
						X.Upserte.toServer(this.pended_changes[i]);
				X.Upserte.unlockSending();
				this.pended_changes = null;
				return true;
			}, this);
			this.sendToServer = function(elm) {
				if(this.pended_changes) { // save in pended changes, if key is not ready
					this.pended_changes[elm.$.name] = elm; //unique!
				} else {
					X.Upserte.toServer(elm);
				}
			}
			return this;
		},
		new_key: function(cont, table_node, vals) { return new this.key(cont, table_node, vals); }
	}
		//TODO: move to prototype
		//array: 1) combo items -> filled in filter -> so in select when data come 
		// 2) subitems -> filled in select  when data come
		// one object filled in select when data come
})(X.DBdefaultEnv);

X.Select = (function(env) {
	function new_command(cmd) {
		cmd.keyObject = cmd.keyObject.target;
		cmd.object = null;
		return cmd;
	}
	function prepareSending(elem, cont) {
		cont.object = elem;
		return cont;
	}
	function prepareSelect(c, queue) {
		if(c.object) {
			var temp_node = null;
			c.object.node = c.object.node || ( temp_node = X.modelBuilder.appendElement(c.object.target) );
			var sql = X.sql.makeSelect(c.object.node, X.OID(c.keyObject));
			if(temp_node)
				c.object.target.remove(temp_node);
			queue.push(sql);
		}
	}
	function onresponse(ser, cli) {
		if(ser.oid !== X.OID(cli.keyObject)) {
			//TODO: check match!
			alert('oid is not match');
		}
		var elm = cli.object.target;
		if(ser.SUCCESS) {
			var data = ser.RESULTSET;
			if(env.isMulti(elm)) {
				elm.removeAll();
				env.writeArray(elm, data.length ? data : null);
			}
			else {
				if(data.length > 1) 
					env.writeSelectError( elm, JSON.stringify(com) );
				env.writeRecord(elm, data.length ? data[0] : null);
			}
		} else {
			env.writeSelectError( elm, JSON.stringify(ser) );
		}
	}
	function onerror(error) {
		env.onSendError("server responce:", error);
	}
	var queue = X.Queue(env.send.bind(env), 
				prepareSelect, 
				new_command, 
				prepareSending, 
				onresponse, 
				onerror, 0);
	return {
		sendSelect: queue.sendToServer,
		lockSending: queue.lockSending,
		unlockSending: queue.unlockSending,
		withLockedSending: queue.withLockedSending,
		key: function(container, table_node) {
			var self = this;
			self.container = container;
			self.table_node = table_node;
			{
				var ops = self.table_node.linkops;
				for(var i=0;i<ops.length;++i) {
					var fld = env.oko(ops[i].value || ops[i].field);
					fld.keys.push(self);
				}
			}
			function defined_condition(peek_value) {
				var wheres = [];
				var links = [];
				var ops = self.table_node.linkops;
				for(var i=0;i<ops.length;++i) {
					var field =  X.sql.node( env.oko(ops[i].field) );
					var value = ops[i].value && env.oko(ops[i].value);
					var link = null;
					if( peek_value || !value) {
						//join and where differences
						if(!value && env.isMulti( self.container ))
							throw "Error: array is built on backrel with parametrised condition";
						if(env.isMulti( self.container )) {
							value = env.read(value);//TODO:subscribe if updatables, other way - peek
						} else {
							value = value ? env.peek(value) : ops[i].rawvalue;//no subcription. those subscriptions in updatables
						}
						if(X.isEmpty(value))
							value = " IS NULL";
						else {
							link = value;
							value = "=?";
						}
					} else {
						if(env.isMulti( self.container )) {
							link = { field: X.sql.node(value) };
							value = "=?";
						} else
							value = "="+ X.sql.node(value);
					}
					if(link)
						links.push(link);
					wheres.push(field + value);
				}
				return {where: wheres.join(' AND '), link: links}
			}
			self.general = defined_condition( false );
			self.particular = null;
			self.ready = ko.computed(function() {
				var ops = this.table_node.linkops;
				for(var i=0;i<ops.length;++i) {
					var value = env.oko(ops[i].value || ops[i].field);
					if( value && !value.sync() ) //subscribe to sync
						return false;
				}
				this.particular = defined_condition( true );
				return true;
			}, self);
			self.waiting_ready = false;
			self.refresh = function() {
				self.waiting_ready = true;
				self._refresh = ko.computed(function(){
					if(this.ready()) {//subscription to syncs
						X.Select.sendSelect({ target:table_node, node: table_node});
						waiting_ready = false;
					}
				}, self, { disposeWhen: function() { return !self.waiting_ready }});
			}
		},
		new_key: function(cont, table_node) { return new this.key(cont, table_node) }
		/*
		key: function(kv) {
			this.key = kv;
			//rel key field is subcribed via edit using
			this.sync = ko.computed(function() {
				var synced = true;
				var refresh_need = false;
				for(var i in this.key) {
					var elm = env.oko(this.key[i]);
					synced &= elm.sync();//subcribtion to sync changes
					refresh_need |= elm.needKeySelect;
				}
				if(!synced || !refresh_need) 
					return false;
				for(var i in this.key) {
					var elm = env.oko(this.key[i]);
					delete elm.needKeySelect;
				}
				
				var rels = {}
				for(var i in this.key) {
					var fld = env.oko(this.key[i]);
					for(var j in fld.within)
						rels[j] = fld.within[j];
				}
				
				for(var i in rels)
					for(var j in rels[i].joins)
						X.Select.sendSelect({ target:rels[i].joins[j], node: rels[i].joins[j]});
				return true;
			}, this);
		},
		new_key: function(kv) { return new this.key(kv) }*/
	}
})(X.DBdefaultEnv);