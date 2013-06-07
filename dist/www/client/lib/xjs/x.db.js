X.Upserte = (function(env) {
	function newQueue() { return { cmds: [] } }
	var DBQueue = newQueue();
	var sentDBQueue = null;
	var lockSending = 0;
	var cmdsWhenLocked = 0;

	function _processQueue() {
		if(lockSending) { ++cmdsWhenLocked; return; }

		if(DBQueue.cmds.length === 0) return;
		
		var to_send_queue = [];
		
		for(i = 0; i < DBQueue.cmds.length; ++i) {
			var qe = DBQueue.cmds[i];
			var to_send = { 
					table: qe.keyObject.$$.name, 
					key: qe.keyObject.DBKeyValue,
					oid: X.OID(qe.keyObject) 
					}
			if(qe.objects) { //update/insert
				to_send.values = {}
				for(var j in qe.objects) {
					// due to toJSON isCid translated to {cid:val} which is enough
					to_send.values[j] = env.read(qe.objects[j]);
				}
				to_send_queue.push(X.sql.makeUpserte(to_send));
			} else { //delete
				if(qe.keyObject.DBKeyValue) //!DBKeyValue - new
					to_send_queue.push(X.sql.makeUpserte(to_send));
			}
		}
		if(to_send_queue.length) {
			//var j = JSON.stringify(to_send_queue);
			sentDBQueue = DBQueue;
			DBQueue = newQueue();
			env.send(to_send_queue, onresponce, onerror)
		}
	}
	var processQueue = X.throttle(_processQueue, env.interval);
	function sendToServer(elm) {
		var n = { keyObject: elm.key, objects: {} }
		{
			var last = DBQueue.cmds.slice(-1)[0];
			var qe = last && n.keyObject === last.keyObject ? last : n;
			
			if(qe !== last)
				DBQueue.cmds.push(n);
			
			if(elm.key._destroy)
				qe.objects = null;
			
			if(qe.objects && env.isChanged(elm)) { //if not destroyed!
				qe.objects[elm.$.name] = elm;
			}
		}
		processQueue();
	}
	var errorCount = 0;
	function onerror(err) {
		//log...
		env.onSendError("server responce:", err);
		++errorCount;
		//resend... 
		DBQueue.cmds = sentDBQueue.cmds.concat(DBQueue.cmds);
		sentDBQueue = null;
		processQueue();
	}
	function onresponce(cmds) {
		errorCount = 0;
		//isCid NOT used
		//must match!
		var objs = sentDBQueue.cmds;
		for(var t = 0; t < cmds.length; ++t)
		{
			var s = cmds[t]; //
			var c = objs[t].keyObject; //must match previous!
			if(s.oid !== X.OID(c)) {
				//TODO: check match!
				alert('oid is not match');
			}
			if(s.SUCCESS) {
				var values = X.sql.valuesFromUpdate(s);
				if(values) {
					//insert/update
					for(var i in values) {
						var obj = objs[t].objects[i];
						if(obj) {
							env.dbvalue(obj)(values[i]); //from server!!!! cids translated there!!!
							if(env.isChanged(obj))
								env.write(obj, env.dbvalue(obj)); //if server change value
						} else {
							//server send new value, but does't ask it to change
						}
					}
					//recalculate key for whole row
					//DBKeyValue has db values
					var k = {};
					for(var i in c.values) //loop in keyObject
						k[c.values[i].$.name] = env.dbvalue(c.values[i])(); //new DBValue here!
					c.DBKeyValue = k;
				} else {
					//delete
					c.DBKeyValue = null; //clear key value from deleted records
				}
			} else {
				env.writeSaveError(c, JSON.stringify(s));
			}
		}
		sentDBQueue = null;
	}
	return {
		toServer: sendToServer,

		lockSending: function() { ++lockSending; },
		unlockSending: function() { 
					if(--lockSending == 0 && cmdsWhenLocked) {
						processQueue(); cmdsWhenLocked = 0;
					} 
				},
		withLockedSending: function(f) { 
			try { this.lockSending(); return f(); } 
			finally { this.unlockSending(); } 
		},
		
		key: function(DBTableDescr, vals) { // получает { value: KO }
			this.$$ = DBTableDescr;
			//this.DBValue = undefined; //TODO: calc it when we read object from server, and set ready = true
			this.values = vals; //to make DBKeyValue and to trace changes in key 
			this.pended_changes = {}; // objects; pended_changes === null when ready
			this.reload = function() {
				var where = [];
				for(var i in this.values) {
					where.push({field:this.values[i],value:this.values[i]()});
				}
				
			}
			this.ready = ko.computed(function() {
				var k = {};
				var insert_key = true;
				for(var i in this.values) {
					var v = env.read(this.values[i]);
					if(X.isEmpty(v)) return false;
					if(this.values[i].$.pk) {
						v = env.dbvalue(this.values[i])();
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
				if(env.isChanged(elm)) {//changed
					if(this.pended_changes) { // save in pended changes, if key is not ready
						this.pended_changes[elm.$.name] = elm; //unique!
					} else {
						X.Upserte.toServer(elm);
					}
				}
			}
			return this;
		},
		new_key: function(DBTableDescr, vals) { return new this.key(DBTableDescr, vals); }
		
		//TODO: move to prototype
		//array: 1) combo items -> filled in filter -> so in select when data come 
		// 2) subitems -> filled in select  when data come
		// one object filled in select when data come
	}
})(X.DBdefaultEnv);
X.Select = (function(env) {
	var procObjects = {};
	function onresponce(elm, response) {
		for(var i=0;i<response.length;++i) {
			var com = response[i];
			if(com.SUCCESS) {
				if(env.isMulti(elm)) {
					elm.removeAll();
					env.writeArray(elm, com.RESULTSET);
				}
				else {
					if(com.RESULTSET.length != 1) 
						env.writeSelectError( elm, JSON.stringify(com) );
					env.writeRecord(elm, com.RESULTSET[0]);
				}
			} else {
				env.writeSelectError( elm, JSON.stringify(com) );
			}
		}
		delete procObjects[X.OID(elm)];
	}
	function onerror(elm, error) {
		env.onSendError("server responce:", error);
		delete procObjects[X.OID(elm)];
		sendQueryToServer(elm);//resend
	}
	function sendQueryToServer(elm, sql) {
		var oid = X.OID(elm);
		if(!procObjects[oid]) {
			procObjects[oid] = elm;
			env.send(sql, onresponce.bind(this, elm), onerror.bind(this, elm));
		}
	}
	function rel_key(kv) {
		this.key = kv;
		this.changed = ko.computed(function() {
			var all_changed = true;
			for(var i in this.key) {
				var v = env.read(this.key[i]);//subscription
				if(env.isChanged(this.key[i]))
					this.key[i].changed = true;
				if(!this.key[i].changed) 
					all_changed = false;
			}
			if(!all_changed) return false;
			for(var i in this.key)
				delete this.key[i].changed;
			for(var i in this.key) 
				if(this.key[i].value) {
					var c = this.key[i];
					for(var j in c.joins) {
						var sql = X.sql.makeSelect(c.joins[j]);
						X.Select.sendQuery(c.joins[j], sql);
					}
				}
			return true;
		}, this);
	}
	return {
		sendQuery: sendQueryToServer,
		rel_key: rel_key
	}
})(X.DBdefaultEnv);