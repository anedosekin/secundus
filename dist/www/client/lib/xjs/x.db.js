X.table_key = (function(env) {
	function compose(field, value, links) {
		//field - name with table alias
		var link = null;
		if(X.isEmpty(value))
			value = " IS NULL";
		else {
			link = value;
			value = "=?";
		}
		if(link)
			links.push(link);
		return field + value;
	}
	function key(table_name, key_fields, on_delete) {
		var self = this;
		self.pended_changes = {}; // objects; pended_changes === null when ready
		self.key_fields = key_fields;
		self.table_name = table_name;
		self.on_delete = on_delete;
		self.current = ko.computed(function() {
			var wheres = [];
			var links = [];
			var key_fields = this.key_fields;
			var ready = true;
			for(var i in key_fields) {
				//reading current dbvalue
				var v = env.read( env.oko( key_fields[i] ).dbvalue ).value
				if(X.isEmpty( v )) 
					ready = false;
				wheres.push( compose(i, v, links ) );
			}
			if(!ready) return;
			return { where: wheres.join(' AND '), link: links, hash: wheres.join(' AND ')+' '+links.join('" "') }
		}, self)
		self.ready = ko.computed(function() {
			var k = {};
			var ready = true;
			var condition = this.condition;
			for(var i in this.key_fields) {
				ready &= !X.isEmpty( env.read( this.key_fields[i] ) );//subscription to all fields
			}
			if(!ready) return false;
			this.values = this.current();
			/*
			for(var i in key_fields) {
				if(!X.isEmpty( env.read(key_fields[i].dbvalue).value ))
					insert_key = false;//if all dbvalues of key is empty, then insert
			}*/
			//TODO: call expr as get in update
			//this.values = insert_key ? null : this.current();
			
			return true;
		}, self);
		self.sendToServer = function(elm) {
			if(self.pended_changes) { // save in pended changes, if key is not ready
				self.pended_changes[elm.get_name()] = elm;
				if(self.ready()) {
					var u = X.sql.makeUpserte( self );
					for(var i in this.pended_changes)
						X.Q.sync.send( u, this.pended_changes[i] );
					self.pended_changes = null
				}
			} else {
				var u = X.sql.makeUpserte( self );
				X.Q.sync.send( u, self._destroy ? self.on_delete : elm )
			}
		}
		return self;
	}
	function filter(cont, fields, parametrized) {
		//one filter for relation
		//contains value objects from current table and field names from target tables
		var self = this;
		self.container = cont;
		self.fields = fields;
		self.parametrized = parametrized;
		for(var i in fields) {
			if( fields[i].value && env.boundAsUpdatable( env.oko( fields[i].value ) ) )
				cont.boundAsUpdatable = true;
		}
		self.gerenal = function(rel_params) {
			var wheres = [];
			var links = [];
			var raw = self.get_raw_vals( rel_params, "with alias" );
			for(var i in raw) {
				wheres.push( compose(i, raw[i], links) );
			}
			var vals = self.get_vals("with alias");
			for(var i in vals) {
				var value = env.oko( vals[i] );
				var link = null;
				if(env.isMulti( self.container )) {
					link = { field: X.sql.node(value) };
					value = "=?";
				} else
					value = "="+ X.sql.node(value);
				if(link)
					links.push(link);
				wheres.push( i + value );
			}
			return { where: wheres.join(' AND '), link: links }
		}
		self.current = function( rel_params ) {
			var wheres = [];
			var links = [];
			var raw = self.get_raw_vals( rel_params, "with alias");
			for(var i in raw) {
				wheres.push( compose( i, raw[i], links ) );
			}
			var vals = self.get_vals("with alias");
			for(var i in vals) {
				//reading current dbvalue
				wheres.push( compose( i, env.peek( env.oko( vals[i] ).dbvalue ).value, links ) );
			}
			return { where: wheres.join(' AND '), link: links }
		}
		self.get_vals = function(add_alias) {
			// { "alias.name of field node": value node, ... }
			// returns value objects of filter
			add_alias = add_alias || "";
			var vals = undefined
			var ff = self.fields;
			for(var i in ff) {
				if(ff[i].value)
					(vals = vals || {})[ (add_alias&&ff[i].field.alias) + ff[i].field.name ] = ff[i].value
			}
			return vals
		}
		self.get_raw_vals = function( rel_params, add_alias ) {
			// { "alias.name of field node": calculated raw value, ... }
			// returns calculated raw values
			add_alias = add_alias || "";
			var raw = undefined;
			var ff = self.fields;
			var p = 0;
			rel_params = rel_params && rel_params.split(':');
			for(var i in ff) {
				if(!ff[i].value) {
					(raw = raw || {})[ (add_alias&&ff[i].field.alias) + ff[i].field.name ] = ff[i].replace ? rel_params[p++] : ff[i].rawvalue
				}
			}
			return raw;
		}
		self.get_flds = function(add_alias) {
			// { "alias.name of field node": field node, ... }
			// returns all available nodes of filter fields with raw value (only actual for direct relations, not arrays)
			add_alias = add_alias || "";
			var flds = undefined;
			var ff = self.fields;
			for(var i in ff) {
				if(!ff[i].value && ff[i].field.node)
					(flds = flds || {})[ (add_alias&&ff[i].field.alias) + ff[i].field.name ] = ff[i].field.node
			}
			return flds
		}
		self.run_requery = function() {
			var c = self.container;
			if( c.joins ) {
				for(var i in c.joins) {
					var s = X.sql.makeSelect( c.joins[i], self.current( i ) );
					s && X.Q.async.send( s.sql, s.dist )
				}
			}
			else {
				var node = X.modelBuilder.appendElement( c );
				var s = X.sql.makeSelect( node, self.current() )
				s && X.Q.async.send( s.sql, c )
				c.remove( node );
			}
		}
		function waiting( fields ) {
			var df = X.new_defer();
			var wait_write = ko.computed(function() {
				var ready = true;
				for(var i in fields) {
					var fld = env.oko( fields[i] );
					var val = env.read( fld.dbvalue ).value;//subscribe to dbfields
					if( env.isChanged( fld ) )
						ready = false
				}
				if(ready) {
					wait_write.dispose();
					df.resolve();
				}
				return ready;
			});
			return df.promice;
		}
		self.requery_waiting = function( fields ) {//we are waiting fields when they get their values from db
			waiting( fields ).then( self.run_requery ).done();
		}
		return self;
	}
	return {
		new_key: function(cont, table_node) {
			var key_fields = {};
			for(var i in table_node) {
				var fld = table_node[i];
				if(fld && fld.$ && fld.$.pk)
					key_fields[ fld.$.name ] = env.oko( fld )//field name without aliases - for update
			}
			return new key(
				table_node.$$.name, 
				key_fields, 
				function() {
					cont.remove(table_node);
					this.particular = null;
					//key.DBKeyValue = null; //clear key value from deleted records
					
				});
		},
		new_filter: function(cont, table_node) {
			//table_node is example for alias, it's not stored
			var filter_fields = [];
			var parametrized = false;
			if(cont.$.condition) {
				for(var i=0;i<cont.$.condition.length;++i) {
					var c = cont.$.condition[i];
					var field = table_node[ env.isMulti(cont) ? c.point : c.target ];
					//Array table_node - is testing node, so it's not adequate
					//for Array field.node is fictive, and so it get undefined value here
					var op = {
						field: {
							alias:field.node.alias+".",
							name: field.$.name,
							node: env.isMulti( cont ) ? null : field
						},
						value: cont.node[ env.isMulti(cont) ? c.target : c.point ] 
					}
					if(c.value) {//rawvalue
						if(c.value==="?")
							op.replace = true;
						else
							op.rawvalue = c.value;
						
						parametrized = true;
					}
					filter_fields.push(op);
				}
			}
			return new filter(cont, filter_fields, parametrized) 
		},
		auto_requery: function( filter ) {
			var vv = filter.get_vals();
			if(vv && ! filter.auto_val_requery) {
				filter.auto_val_requery =  filter.auto_val_requery || 
				ko.computed(function() {//based on values change
					var ready = true;
					for(var i in vv) {
						var fld = env.oko( vv[i] ).dbvalue;
						var val = env.read( fld );//subscribe to fields
						if( ready && !val.update )
							ready = false
					}
					if( ready )
						this.run_requery();
					return ready;
				}, filter);
			}
			var ff = filter.get_flds();
			if(ff && !filter.auto_fld_requery) {
				filter.auto_fld_requery = 
					ko.computed(function() {//based on values change
						var ready = true;
						for(var i in ff) {
							var fld = env.oko( ff[i] ).dbvalue;
							var val = env.read( fld );//subscribe to fields
							if( ready && !val.update )
								ready = false
						}
						if( ready )
							this.run_requery();
						return ready;
					}, filter);
			}
		}
	}
})(X.DBdefaultEnv)

X.Q = (function(env) {
	//queue = [ {key:"sqltext", select: sql_object, selects: [ node, array ]}] || queue = [{key: tablenode.key, upsertes:{ elm.$.name: elm, ... }]
	function find_sync( queue, key) {
		var last = queue.length && queue.slice(-1)[0];
		return last && key === last.key ? last : null
	}
	function find_async( queue, key) {
		for(var i in queue) {
			if(queue[i].key == key)
				return queue[i]
		}
	}
	function Lock( waiting ) {
		var self = this;
		if(waiting)
			self.waitingAnswer = false;
		self.find = waiting ? find_sync : find_async;
		self.queue = [];
		self.errorCount = 0;
		self.sendingLock = false;
		self.cmdsWhenLocked = 0;
		self.lockSending = function() { ++self.sendingLock; },
		self.unlockSending = function() { 
			if(--self.sendingLock == 0 && self.cmdsWhenLocked) {
				self.process_queue(self.queue, self); 
				self.cmdsWhenLocked = 0;
			} 
		}
		self.withLockedSending = function(f) { 
			try { self.lockSending(); return f(); } 
			finally { self.unlockSending(); } 
		}
		self._process_queue = function() {
			var lock = self;
			var queue = lock.queue;
			if( lock.sendingLock ) { ++lock.cmdsWhenLocked; return; }
			if( queue.length === 0 || lock.waitingAnswer ) return;
			var to_send = [];
			var sent = [];
			for(var i in queue) {
				var q = queue[i];
				var sql = q.sql;
				if( {"UPDATE":true,"INSERT":true}[ sql.TYPE ] ) {
					//getting freshest values just before sending
					for(var j in q.elems) {
						var koo = q.elems[j]
						if(env.isChanged( koo ))
							X.sql.addField( sql, koo.get_name(),env.read( koo ) )
					}
				}
				if( sql ) {
					to_send.push( sql );
					sent.push( q );
				}
			}
			if(to_send.length) {
				queue.splice(0, queue.length );
				env.send(to_send, self.onresponse.bind(self, sent), self.onerror.bind(self, sent));
				if(lock.waitingAnswer != undefined) 
					lock.waitingAnswer = true;
			}
		}
		self.onresponse = function(cli, ser) {
			for(var i in cli)
				self[ cli[i].sql.TYPE ]( ser[i], cli[i].elems );
			if(self.waitingAnswer) {
				self.waitingAnswer = false;
				self.process_queue();
			}
		}
		self.process_queue = X.throttle(self._process_queue, env.interval || 0);
		self.onerror = function(cli, ser) {
			//log...
			env.onSendError("server responce:", JSON.stringify( ser ));
			++lock.errorCount;
			//resend...
			self.queue = self.queue.concat( cli );
			self.process_queue();
		}
		function write_dist(dist, data) {
			//dist = [koo, koo, koo, array]
			//data = ["x","x","x"]
			for(var i in dist) {
				if(dist[i].append) {
					write_array(dist[i], data && data[i]);
				} else
					dist[i].write( data && data[i] )
			}
		}
		function write_array( arr, data ) {
			arr.before_fill && arr.before_fill();
			for(var i in data) {
				write_dist(arr.append(), data[i])
			}
		}
		self.SELECT = function(resp, dists) {
			if(resp.SUCCESS) {
				for(var j in dists) {
					//dists = [ [koo,koo,koo,array], array, array, [koo, koo, array] ]
					var rez = resp.RESULTSET || [];
					if( dists[j].append )
						write_array( dists[j], rez )
					else {
						if(rez.length>1) 
							console.log("warning! node received more than 1 record")
						write_dist( dists[j], rez.length && rez[0] || null );
					}
				}
			} else {
				env.writeSelectError( null, JSON.stringify( resp ) );
			}
		}
		function update(resp, dists) {
			var values = X.sql.valuesFromUpdate( resp );
			X.Q.async.lock();//stack all refresh selects
			for(var j in values) {
				var koo = dists[j];
				if(koo) {
					koo.update( values[j] );//from server!!!!
					koo.update_similar( values[j] );
				} else {
					//TODO: server send new value, but does't ask it to change
				}
			}
			//key will be recalcutated by subscription
			X.Q.async.unlock();
		}
		self.UPDATE = function(resp, dists) {
			if(resp.SUCCESS)
				update(resp, dists)
			else {
				for(var j in dists)
					dists[j].update_back();
				env.writeSaveError(null, JSON.stringify( resp ));
			}
		}
		self.INSERT = function(resp, dists) {
			if(resp.SUCCESS)
				update(resp, dists)
			else {
				env.writeSaveError(null, JSON.stringify( resp ));
			}
		}
		self.DELETE = function(resp, dists) {
			//dists = [ on_delete,... ]
			if(resp.SUCCESS) {
				for(var i in dists)
					dists[i]();
			} else {
				env.writeSaveError(null, JSON.stringify( resp ));
			}
		}
		self.send = function( sql, elem ) {
			//sql - sql_structure
			//select:
			//1) elem = [koo, koo, koo, array_object, koo], koo.write
			//2) elem = array_object; array_object.append returns [koo, koo, koo], koo.write; array_object.before_fill
			//array_object is array when has 'append'
			//update/insert:
			//1) elem = koo, koo.update, koo.update_back, koo.update_similar, koo.get_name
			//delete:
			//1) elem = on_delete function
			var n = { key: JSON.stringify( sql ), sql: sql, elems:[] }
			var qe = self.find(self.queue, n.key) || (self.queue.push(n), n)
			if( {"UPDATE":true,"INSERT":true}[ sql.TYPE ] )
				qe.elems[ elem.get_name() ] = elem;
			else
				qe.elems.push( elem )
			self.process_queue();
		}
		return self;
	}
	function Queue( lock ) {
		this.send = lock.send;
		this.lock = lock.lockSending;
		this.unlock = lock.unlockSending;
		this.withlock = lock.withLockedSending;
		return this;
	}
	return {
		sync: new Queue( new Lock(true) ),
		async: new Queue( new Lock(false) )
	}
})(X.DBdefaultEnv);