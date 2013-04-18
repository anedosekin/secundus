if(!String.prototype.trim) String.prototype.trim = function () { return this.replace(/^\s+|\s+$/g,''); }
if(!String.prototype.trimLeft) String.prototype.trimLeft = function () { return this.replace(/^\s+/,''); };
if(!String.prototype.trimRight) String.prototype.trimRight = function () { return this.replace(/\s+$/,''); };
var X = {};
X.isFunction = function(obj) { return typeof obj === 'function'; }
X.isObject = function(obj) { return obj === Object(obj); }
X.isArray = Array.isArray || function(obj) { return toString.call(obj) == '[object Array]'; }
X.isEmpty = function(val) { return val === undefined || val === null || val === ""; }
function G_regEvent(name, phase, f) {
	var freg = function(event) {
		var e = event || window.event;
		var target = e.target || e.srcElement;
		f(e, target);
	}
	var obj = { 'message': window }[name] || document;
	if (obj.addEventListener) obj.addEventListener(name, freg, phase);
	else if( obj.attachEvent ) obj.attachEvent('on'+(name==='focus'?'focusin':name), freg);
}
X.throttle = function(func, wait) {
	var context, args, timeout, result;
	var previous = 0;
	var later = function() {
		previous = new Date;
		timeout = null;
		result = func.apply(context, args);
	};
	return function() {
		var now = new Date;
		var remaining = wait - (now - previous);
		context = this;
		args = arguments;
		if (remaining <= 0) {
			clearTimeout(timeout);
			timeout = null;
			previous = now;
			result = func.apply(context, args);
		} else if (!timeout) {
			timeout = setTimeout(later, remaining);
		}
		return result;
	};
};
X.OID = (function() { var oid = 0; 
	return function(obj) { return obj.oid || (obj.oid = "-" + ++oid); }
})();
X.cid = {
	seq: "0",
	reset: function() { this.seq = "0"; },
	make: function() {
		//increment seq in string form!
		this.seq.match(/^([^-]*?)([^-]??)(-*)$/);
		this.seq = RegExp.$1+
			this.encodechars.charAt(RegExp.$2?this.encodechars.indexOf(RegExp.$2)+1:0)+
			RegExp.$3.replace(/-/g,"0");
		var r = Object(this.seq);
		r.isCid = true;
		r.toJSON = function() { return {cid: this.valueOf()}; }
		return r;
	},
	encodechars: "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-",
	encode64: function(num,len) { len = len || 0;
		var res = ""; 
		while(num||--len>=0) {
			res = this.encodechars.charAt(num%64)+res;
			num = num/64>>0;
		}
		return res;
	},
	platform: "00",
	seed: function() {
		var dt = new Date;
		var y = dt.getUTCFullYear() - 2000;
		var m = dt.getUTCMonth();
		var d = dt.getUTCDate()-1;
		var H = dt.getUTCHours();
		var M = dt.getUTCMinutes();
		var S = dt.getUTCSeconds();
		return this.encode64(( ((y*366+m)*31+d)*24*60*60 + (H*60+M)*60+S ), 6 )+this.platform;
	},
	encode: function(scid,lcid) {
		//scid: YYYYMMDDhhmmssnum => 
		//   b64(SimpleDataTimeConv)+plat+b32(scid.num/32)+b32(scid.num%32+32)+b64(lcid)
		var res = this.seed();
		var n = parseInt(scid);
		res += n.toString(32).slice(0,-1).toUpperCase()+this.encodechars.charAt(n%32+32);
		return res + lcid;
	}
}
X.asyncCall = (function() {
		var dc = "defer call";
		var msgs = [];

		G_regEvent('message', false, function(e) {
			if(e.source == window && e.data === dc) {
				var nmsgs = msgs.splice(0,msgs.length);
				var f;
				while(f = nmsgs.shift()) 
					try {
						f();
					} catch(err) {
						X.log("async:", err);
					}
			}
		});

		return function(f) {
			if (!X.isFunction(f))
			 throw new TypeError("asyncCall - what is trying to be used for not callable");
			if(msgs.push(f)==1)
				window.postMessage(dc, "*")
		}
})();
X.new_defer = function() {
		function check(promice) {
			if(promice.then.done)
				throw "resolving alredy resolved promice";
		}
		var process;
		function new_promice() {
			return { then: function (ok,err) 
				{ if(!this.then.calls) this.then.calls = [];
					var ret = new_promice();
					this.then.calls.push(
						{resolve: ok, reject: err, ret: ret }
					)
					if(this.then.done) {
					  //alredy processed
					  X.asyncCall( 
					    process.bind(ret, this.then.done, this.then.value, true) 
					  );
					}
					return ret;
				},
			  fail: function(f) { return this.then(null, f) },
			  done: function(f) { return this.then(f, null) }
			}
		}
		process = function(reason, promice, val, again) { !again && check(this);
			if(!then.calls && !then.chain && reason === 'reject')
				throw val;
			//console.log('process '+reason)
			var then = this.then;
			then.done = reason;
			then.value = val;
			var calls = then.calls ? then.calls.splice(0) : [];
			var e;
			while(e = calls.shift()) {
				var nreason = reason;
				var nval = val;
				if(e[reason])
					try {
						nval = e[reason](val);
						nreason = 'resolve';
					} catch(err) { 
						nval = err; 
						nreason = 'reject';
					}
				if (nval && X.isFunction(nval.then)) {
					//chain
					nval.then.chain = e.ret;
				} else
					process.call(e.ret, nreason, nval);
			}
			if(then.chain)
				process.call(then.chain, reason, val); 
		}
	return {
		resolve: function(val) { 
			check(this.promice);
			X.asyncCall( process.bind(this.promice, 'resolve', val ) );
		},
		reject: function(val) { check(this.promice);
			X.asyncCall( process.bind(this.promice, 'resolve', val ) );
		},
		promice: new_promice()
	}
}

X.XHR = function(method, url, content, headers) {
	var df = X.new_defer();

	var xhr = new XMLHttpRequest();
	xhr.open(method, url, true);
	if(headers)
		for(var i in headers)
			xhr.setRequestHeader(i, headers[i]);
	//xhr.setRequestHeader("",)//accept and so on
	xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
	xhr.send(content || null);
	xhr.onreadystatechange = function() {
	  if (xhr.readyState === 4) { // request complete
		if (xhr.status === 200 || xhr.status === 0 && xhr.responseText) {
			// done
			//TODO: md5 check
			df.resolve(xhr.responseText);
		}
		else { // fail
			df.reject(xhr.status+" "+xhr.statusText);
		}
	  }
	}
	return df.promice;
}