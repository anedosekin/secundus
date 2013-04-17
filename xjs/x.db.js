X.DB = (function(env) {
	function newQueue() { return { cmds: [] } }
	var DBQueue = newQueue();
	var sentDBQueue = null;
	var lockSending = 0;
	var cmdsWhenLocked = 0;

	function _processQueue() {
		if(lockSending) { ++cmdsWhenLocked; return; }

		if(DBQueue.cmds.length === 0) return;
		
		var to_send_queue = { seed: X.cid.seed(), cmds: [] }
		
		for(i = 0; i < DBQueue.cmds.length; ++i) {
			var qe = DBQueue.cmds[i];
			var to_send = { table: qe.keyObject.$.name, 
					key: qe.keyObject.DBKeyValue,
					oid: X.OID(qe.keyObject) 
					}
			if(qe.objects) { //update/insert
				to_send.values = {}
				var hasChanges = false;
				for(var j in qe.objects) {
					// due to toJSON isCid translated to {cid:val} which is enough
					//if(env.isChanged(qe.objects[j])) //changed
					to_send.values[hasChanges = j] = env.read(qe.objects[j]);
				}
				if(hasChanges)
					to_send_queue.cmds.push(to_send);
			} else { //delete
				if(qe.keyObject.DBKeyValue) //!DBKeyValue - new
					to_send_queue.cmds.push(to_send);
			}
		}
		if(to_send_queue.cmds.length) {
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
			
			if(qe !== last && (elm.key._destroy || env.isChanged(elm)))
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
	function onresponce(txt) {
		errorCount = 0;
		var js = JSON.parse(txt); //isCid NOT used
		//must match!
		var a = js.cmds;
		var objs = sentDBQueue.cmds;
		for(var t = 0; t < a.length; ++t)
		{
			var s = a[t]; //
			var c = objs[t].keyObject; //must match previous!
			if(s.oid !== X.OID(c)) {
				//TODO: check match!
				alert('oid is not match');
			}
			if(s.error)
				env.writeSaveError(c, s.error);
			else
				if(s.values) {
					//insert/update
					for(var i in s.values) {
						var obj = objs[t].objects[i];
						if(obj) {
							obj.DBValue = s.values[i]; //from server!!!! cids translated there!!!
							if(env.isChanged(obj))
								env.write(obj, obj.DBValue); //if server change value
						} else {
							//server send new value, but does't ask it to change
						}
					}
					//recalculate key for whole row
					//DBKeyValue has db values
					var k = {};
					for(var i in c.values) //loop in keyObject
						k[c.values[i].$.name] = c.values[i].DBValue; //new DBValue here!
					c.DBKeyValue = k;
				} else {
					//delete
					c.DBKeyValue = null; //clear key value from deleted records
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
			this.$ = DBTableDescr;
			//this.DBValue = undefined; //TODO: calc it when we read object from server, and set ready = true
			this.values = vals; //to make DBKeyValue and to trace changes in key 
			this.pended_changes = {}; // objects; pended_changes === null when ready
			this.ready = ko.computed(function() {
				var k = {};
				var key_not_ready = false;
				for(var i in this.values) {
					var v = env.read(this.values[i]);//making dependencies
					if(this.values[i].$.pk) 
						v = this.values[i].DBValue;
					k[this.values[i].$.name] = v;
					if(X.isEmpty(v)) key_not_ready = true;
					//if(X.isEmpty(v)) return false;
				}
				if(key_not_ready) return false; // has empty val -> not a pk! so, not ready
				this.DBKeyValue = k;
				if(this.pended_changes)
					for(var i in this.pended_changes)
						X.DB.toServer(this.pended_changes[i]);
				this.pended_changes = null;
				return true;
			}, this);
			this.sendToServer = function(elm) {
				if(this.pended_changes) { // save in pended changes, if key is not ready
					this.pended_changes[elm.$.name] = elm; //unique!
				} else {
					X.DB.toServer(elm);
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