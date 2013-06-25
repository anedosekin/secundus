//--

/*
	textContent = V
	getAttribute = A
	getIntAttribute = Ai
	getElementById = I
	querySelector = QS
		All	QSA
запросы (на атрибуты - ими всегда можно разметить!)
	вверх: P
	назад: B
	вперед: F
	вниз:	Q - но надо не входить в спец. области

проверка атрибута hasA
инкремент атрибута incA
переключение атрибута
		toggleA (1 значение - remove, 2 значения - переключение)
установка атрибута
		setA

нужные запросы
	вверх: атрибут или тэг P, Pt
		множество атрибутов Pa с объектом
		можно компилировать и кэшировать
		надо не выходить из спец. области
	назад/вперед: атрибут (тэг НЕ нужен, видимо)
		при этом надо подниматься вверх (но не выше определенного уровня!)
		и заходить вниз
		B(attr, up_attr) (для наших целей up_attr = attr вроде бы)
*/

/*

еще надо разбор строки аргументов

*/

// GLOBALS 

function safeParseInt(s) { return parseInt(s) || 0; }

function I(id) { return document.getElementById(id) }
function B() { return document.body }
function QS(s) { return document.querySelector(s); }
function QSA(s) { return document.querySelectorAll(s); }

var toggle = "toggle";

//

window.safeClose = function ( closeParent ) {
	/* killFrame(parent, frameElement) || */
	this.open('javascript:"<script'+'>close()<'+'/script>"',closeParent? '_parent': '_self');
}

if(!String.prototype.trim) String.prototype.trim = function () { return this.replace(/^\s+|\s+$/g,''); }
if(!String.prototype.trimLeft) String.prototype.trimLeft = function () { return this.replace(/^\s+/,''); };
if(!String.prototype.trimRight) String.prototype.trimRight = function () { return this.replace(/\s+$/,''); };
String.prototype.trimBOM = function() { return (document.all&&!window.opera)? this : this.substr(this.indexOf('<')) }
String.prototype.beginsWith = function(s) { return this.length >= s.length && this.substr(0, s.length) === s; }

String.prototype.Div = function(div, div_empty) { return this!=""? this+div : 
								div_empty !== undefiend? div_empty : ""; }

Element.prototype.getXYsum = function() {
		var r = { x: 0, y: 0 }
		var e = this;
		do {
			var w = (e.ownerDocument.parentWindow || e.ownerDocument.defaultView);
			do { 
				r.x += (e.offsetLeft>=0 ? e.offsetLeft : 0); 
				r.y += (e.offsetTop>=0 ? e.offsetTop : 0); 
			} while(e = e.offsetParent);
		} while( w != window && (e = w.frameElement) && e.tagName == "IFRAME" );
		return r;
	}

Element.prototype.getXY =  function () {
	var elem = this;
	var box = elem.getBoundingClientRect();

	var body = document.body;
	var docElem = document.documentElement;

	var scrollTop = window.pageYOffset || docElem.scrollTop || body.scrollTop;
	var scrollLeft = window.pageXOffset || docElem.scrollLeft || body.scrollLeft;

	var clientTop = docElem.clientTop || body.clientTop || 0;
	var clientLeft = docElem.clientLeft || body.clientLeft || 0;

	var top  = box.top +  scrollTop - clientTop;
	var left = box.left + scrollLeft - clientLeft;

	return { x: Math.round(left), y: Math.round(top) }
}


Element.prototype.A = function(name) { return this.getAttribute(name); }
Element.prototype.setA = function(name, v) { this.setAttribute(name, v); return this; }

Element.prototype.Ai = function(name) { var v = this.A(name); return v? parseInt(v) : 0; }
Element.prototype.incA = function(name, diff) { diff = diff || 1; 
		var v = this.Ai(name); return this.setA(name, v + diff); 
	}
Element.prototype.hasA = function(name) { var v = this.A(name); return v!=null? this : null; }
Element.prototype.toggleA = function(name, val1, val2) { 
	var v = this.A(name);
	if(val2 != undefined)
		return this.setA(name, v == val1 ? val2 : val1);
	else
		if(v == val1)
			this.removeAttribute(name);
		else
			this.setAttribute(name, val1);
	return this;
}

Element.prototype.regE = function(name, str) {
	if(this.A('on'+name)) this.setA(this.getA('on'+name)+';'+str);
	else this.setA('on'+name, str);
	return this;
}

Element.prototype.evalHere = function(str) { return eval(str[0]=='@'?this.getA(str.substr(1)):str); }


Element.prototype.V = function() { 
	return "value" in this ? this.valus : "innetText" in this? this.innerText : this.textContent; 
}

Element.prototype.setV = function(v) { 
	if("value" in this) this.value = v;
	else if("innetText" in this) this.innerText = v;
	else this.textContent = v;
	return this;
}

Element.prototype.removeIt = function() { this.parentNode.removeChild(this); return this; }
Element.prototype.closeIt = function() { 
	if(this.hasA("display"))
		this.setD(false);
	else
		this.removeIt();
	if(this.hasA("modal")) { 
		var cover = I("coverBox");
		if(cover.currentModal = this.prevModal)
			cover.style.zIndex = safeParseInt(this.prevModal.style.zIndex)-1;
		else
			cover.removeIt();
	}
	return this;
}

Element.prototype.QS = function(s) { return document.querySelector(s); }
Element.prototype.QSA = function(s) { return document.querySelectorAll(s); }

Element.prototype.P = function(a) {
		var e = this;
		while(e && e.nodeType == 1 && !e.hasA(a)) e = e.parentNode;
		return e.nodeType == 1 ? e : null;
}
Element.prototype.Pt = function(tag,a) {
		var e = this;
		while(e && e.tagName != tag && !(!a || e.hasA(a)))
			e = e.parentNode;
		return e.type == 1 ? e : null;
}

Element.prototype.setD = function(st) {
	if(st === toggle)
		this.setD(this.getA("display")=="N");
	else
		this.setA("display",st?"Y":"N");
	return this;
}

Element.prototype.setDC = function(st) {
	if(st === toggle)
		this.setDC(this.getA("display_content")=="N");
	else
		this.setA("display_content",st?"Y":"N");
	return this;
}

Element.prototype.safeFocus = function () { this.focus(); }

Element.prototype.showXModal = function(elm_to_pos, props) {
	//attr: close_on_esc, close_box, quick_close
	// to_pos - window/elm/null
	// props: pos:top/left/bottom/right/center

	var cover = I("coverBox");
	if(!cover) {
		cover = document.createElement("DIV");
		cover.id = "coverBox";
		B().appendChild(cover);
	}

	this.prevModal = cover.currentModal;
	cover.currentModal = this;

	this.style.zIndex = this.prevModal? safeParseInt(this.prevModal.style.zIndex) + 2 : 10000;

	cover.style.zIndex = safeParseInt(this.style.zIndex)-1;

	this.setD(true);
	var tf = this.QS("A,INPUT");
	tf.safeFocus();
	return this;
}

function blockEvent(e) {
	if (e.stopPropagation) e.stopPropagation()
	else e.cancelBubble=true
	if (e.preventDefault) e.preventDefault()
	else e.returnValue = false
	return e;
}
function getMouseDocPos(e) {
	var posx = 0;
	var posy = 0;
	if (!e) var e = window.event;
	if (e.pageX || e.pageY) {
		posx = e.pageX;
		posy = e.pageY;
	}
	else if (e.clientX || e.clientY) 	{
		posx = e.clientX + document.body.scrollLeft
			+ document.documentElement.scrollLeft;
		posy = e.clientY + document.body.scrollTop
			+ document.documentElement.scrollTop;
	}
	// posx and posy contain the mouse position relative to the document
	return { x: posx, y: posy };
}

//globalEvents

/*@cc_on
(function() {
	function f() {
			var a = document.getElementsByTagName("*");
			for(var i = 0; i < a.length; ++i)
				a[i].className = a[i].className;
			var df = f.fc;
			f.fc = null;
			if(df) df.focus();
		}
	Element.prototype.safeFocus = function () { 
		try { this.focus(); }
		catch(e) { f.fc = this; }
	}
	setInterval( f, 100);
})();
@*/


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

lastFocusedElement = null;
G_regEvent('focus', true, function ( e, togo ) {
		if(togo) {
			if(window.lastFocusedElement && 
				window.lastFocusedElement.P("modal") &&
				!togo.P("modal")
				)
				window.lastFocusedElement.focus();
			else
				window.lastFocusedElement = togo;
		}
});

G_regEvent('click', false, function ( e, elm ) {
	if(elm.hasA("closeBox")) {
		var etest = document.createElement("DIV");
			etest.id = "testPopuframe";
			etest.style.top = 0;
			etest.style.left = "-1px";
			etest.style.height = "0px";
			etest.style.width = elm.offsetWidth+"px";
			etest.setA("closeBox","Y");
		elm.insertBefore(etest, elm.firstChild);
		var tst = document.elementFromPoint(e.clientX,e.clientY);
		etest.removeIt();
		if(tst && tst.id == etest.id)
			elm.closeIt();
	
	} 
	else if(elm.id == "coverBox") {
		var qc = lastFocusedElement && lastFocusedElement.P("modal").hasA("quickClose");
		if(qc)
			qc.closeIt();
	}
});

G_regEvent('keydown', false, function ( e, elm ) {
	if(e.keyCode == 27) {
		var ec = elm.P("quickClose") || elm.P("closeOnEsc");
		if(ec)
			ec.closeIt();
	}
});


String.prototype.URLParam = function (param, def)
	{
		var url = this;
		var re = new RegExp( "[?&]" + param + "=([^&]*)" );
		var mm = url.match(re);
		if( mm )
			return decodeURIComponent( mm[1] );
		return def;
	}

String.prototype.setURLParam =  function( param, val )
	{
		var url = this;
		if( !param )
			alert( "SetUrlParam: empty param name" )

		val = encodeURIComponent( val );

		var re = new RegExp( "(.*[?&]" + param + "=)[^&]*(.*)" );
		var m;
		if(m = url.match(re))
			return m[1] + val + m[2];
		m = url.match(/^(.*\?)(.*)/)
		return (m? m[1] + m[2].Div("&") : url+"?")
			+ param + "=" + val;
	}

/*
	полезные парсинг
	1) парсить с учетом обязательных свойств (может быть имя или массив)
		для таблиц - тип, заголовок
	2) парсить массивы
	3) парсить без пробелов, пробел - разделитель
		экранировать можно кавычками или еще как-то
	имя или имя двоеточие строка в кавычках или имя двоеточие строка без пробелов или с экараном пробела
*/

String.prototype.parseXON = function(params) {
	var c = String.prototype.parseXON.cache[":"+params+":"+s];
	if(c) return c;

	var s = this;
	var res = {};

	var pparsed = params? params.split(" ") : [];

	var rev = /(?:([^='"]\S*)|"([^"]*(?:""[^"]*)*)"|'([^']*(?:''[^']*)*)'|=([^;]*(?:;;[^;]*)*)(?:;|$))/g;
	var p;
	while(p = pparsed.shift()) {
		var m;
		if(m = rev.exec(s)) {
			var val =
				m[1]? m[1] :
				m[2]? m[2].replace(/""/g,'"') :
				m[3]? m[3].replace(/''/g,"'") :
				m[4]? eval(m[4].replace(/;;/g),";") :
				""
			;
			res[p] = val;
		} else
			throw "no value for '"+p+"' in "+s;
	}

	var re = /\s*(\w+)(?:\:\s*(?:([^='"]\S*)|"([^"]*(?:""[^"]*)*)"|'([^']*(?:''[^']*)*)'|=([^;]*(?:;;[^;]*)*)(?:;|$)))?/g;
	re.lastIndex = rev.lastIndex;
	var m = null;
	var vas_eval = false;
	while (m = re.exec(s))
	{
		var name = m[1];
		var val =
			m[2]? m[2] :
			m[3]? m[3].replace(/""/g,'"') :
			m[4]? m[4].replace(/''/g,"'") :
			m[5]? (vas_eval=true), eval(m[5].replace(/;;/g),";")
		:
			true;
		res[m[1]] = val;
	  //console.log(m[1]+"- 2:"+m[2]+ " 3:"+m[3]+" 4:"+m[4]+" 5:"+m[5]);
	}

	if(!vas_eval)
		String.prototype.parseXON.cache[":"+params+":"+s] = res;

	return res;
}
String.prototype.parseXON.cache = {}

Number.prototype.prettyFormat = function(digits, delims) {
	if(isNaN(this)) return "";
	delims = delims || " .";
	var m = this.toString().match(/(\d*)(?:\.(\d*))?/);
	var m1 = m[1] || "0";
	var m2 = m[2] || "";
	m1 = m1.replace(/(\d)(?=(\d\d\d)+(?!\d))/g, '$1'+delims[0])
	if(digits && m2.length > digits) { 
		var m2x = (parseInt(m2.substr(0,digits+1))+5)/10;
		m2 = m2x.toString();
	}
	return m1+(m2?(delims[1]||".")+m2:"");
}

// modal
//	1) close on click outside / lost focus on window (no close button)
//	2) close with button and esc
//	3) close with button only
//	if 2-3 open when 1 open, it closed!
//	submenu hover or click and download

var X = {};
X.isFunction = function(obj) { return typeof obj === 'function'; }
X.isObject = function(obj) { return obj === Object(obj); }
X.isArray = Array.isArray || function(obj) { return toString.call(obj) == '[object Array]'; }
X.isEmpty = function(val) { return val === undefined || val === null || val === ""; }

if (!Function.prototype.bind) {
  Function.prototype.bind = function (oThis) {
    if (!X.isFunction(this)) {
      // closest thing possible to the ECMAScript 5 internal IsCallable function
      throw new TypeError("Function.prototype.bind - what is trying to be bound is not callable");
    }
    var fToBind = this, 
        fNOP = function () {},
        nThis = this instanceof fNOP && oThis
                         ? this
                         : oThis;

    if(arguments.length==1)
    var fBound = function () {
          return fToBind.apply(nThis, arguments);
        };
    else {
    var aArgs = Array.prototype.slice.call(arguments, 1), 
        fBound = function () {
          return fToBind.apply(nThis,
                               aArgs.concat(Array.prototype.slice.call(arguments)));
        };
    }
 
    fNOP.prototype = this.prototype;
    fBound.prototype = new fNOP();
 
    return fBound;
  };
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

X.delay = function(ms) {
	var df = X.new_defer();
	setTimeout(df.resolve.bind(df) , ms);
	return df.promice;
}

X.defer = function(fn) {
	var df = X.new_defer();
	X.asyncCall( function() { 
		try { df.resolve(fn()) }
		catch(err) { df.reject(err) }
	});
	return df.promice;
}

Function.prototype.once = function() {
	var fn = this;
	var called = false;
	var value;
	return function() {
		if(called) return value;
		called = true;
		return value = fn.apply(this, arguments);
	}
}

  // Returns a function, that, when invoked, will only be triggered at most once
  // during a given window of time.
  //
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

  // Returns a function, that, as long as it continues to be invoked, will not
  // be triggered. The function will be called after it stops being called for
  // N milliseconds. If `immediate` is passed, trigger the function on the
  // leading edge, instead of the trailing.
  X.debounce = function(func, wait, immediate) {
    var timeout, result;
    return function() {
      var context = this, args = arguments;
      var later = function() {
        timeout = null;
        if (!immediate) result = func.apply(context, args);
      };
      var callNow = immediate && !timeout;
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
      if (callNow) result = func.apply(context, args);
      return result;
    };
  };

//опции send:JSON, recv:JSON,
//send: wrap long gets

//packed JSON!
// в нем мы убираем имена полей в потоки
// хотя, может быть, достаточно zip?

X.parseOpts = function(o) { return X.isObject(o)? o : typeof o === 'string'? o.parseXON() : {}; }

// базисный запрос
// кодировку url & param делаем в другом месте (до)
// расшифровку - после

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

X.log = function() {
	if(window.console && window.console.log)
		window.console.log.apply(window, arguments);
}


/*
	синхронизация с базой
	1) в модели храним
		текущее значение (оно синхронно с отображением)
		последнее посланное значение
		последние значение из базы
	2) при изменении в модели пишем в отчередь отправки
		(заодно можно паковать значения по ключам)
	3) очередь время от времени вычищаем, отправляя на сервер
		и переносим в очередь ожидания
	4) хорошие ответы переводят
		значения из посланного в текущее и в базу
	5) плохие ответ вычищают посланное и высталяют статус
	6) в очередях храним ссылки на исходные объекты, 
		но для значений храним копии, иначе не будет ясно
	7) для сохранения нам нужен общий урл
	8) для чтения мы можем знать root
	9) при этом надо знать еще значения по умолчанию (и фильтры)

*/

/*
	изменения в модели
	1) изменение значения
	2) добавление элемента
	3) удаление элемента
*/
/*
	тут еще надо учесть перекодироку id
	из клиентской в серверную
*/

X.defaults = function(o, d) {
	var def = X.parseOpts(d);
	for(var i in def)
		if(def.hasOwnProperty(i) && o[i] === undefined)
			o[i] = def[i]
	return o;
}
X.mixin = function(src, dst) {
	for(var i in def)
		if(def.hasOwnProperty(i))
			o[i] = def[i]
	return o;
}
X.clone = function(src) {
	if(Object(src)!==src) return src;
	return X.mixin(src, src.constructor());
}
X.extend = function(src, dst) { return X.mixin(src, X.clone(dst)); }

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
		var dt = new Data();
		var y = dt.getUTCFullYear() - 2000;
		var m = dt.getUTCMounth();
		var d = dt.getUTCDate()-1;
		var H = dt.getUTCHours();
		var M = dt.getUTCMinutes();
		var S = dt.getUTCSeconds();
		return this.encode64( ((y*366+m)*31+d)*24*60*60 + (H*60+M)*60+S ), 6 )+this.platform;
	}
	encode: function(scid,lcid) {
		//scid: YYYYMMDDhhmmssnum => 
		//   b64(SimpleDataTimeConv)+plat+b32(scid.num/32)+b32(scid.num%32+32)+b64(lcid)
		var res = this.seed();
		var n = parseInt(scid);
		res += n.toString(32).slice(0,-1).toUpperCase()+this.encodechars.charAt(n%32+32);
		return res + lcid;
	}
}

X.localDB = {
	open: function(name, ver) {},
	executeSQL: function(sql,args) {},
	transaction: function() {},
	transactionRead: function() {}
}

//		openLocalDB: function() {
//			var db = this.localDB = window.openDatabase(this.url, "", "testing db", 1024);
//				if (db.version != "1") {
//					db.changeVersion(db.version, "1", function(tx) {
//						// User's first visit.  Initialize database.
//						var tables = [
//						  { name: "xdata", columns: ["id VARCHAR PRIMARY KEY", "name TEXT"]}
//						];
//				 
//						for (var index = 0; index < tables.length; index++) {
//						  var table = tables[index];
//						  tx.executeSql("CREATE TABLE " + table.name + "(" +
//										table.columns.join(", ") + ");");
//						}
//					}, null, 
//					function() { /*loadData(db);*/ });
//			} else {
//				/*loadData(db)*/
//			}
//		},


X.DBdefaultEnv = {
		//elm - observable
		//read from observable
		read: function(elm) { return elm.value; },
		//write to observable
		write: function(elm, value) { elm.value = value; },
		//mark observable as it has error when data came fro server
		//(usuccessfull try to write data)
		writeSaveError: function(elm, err) { alert(err); /*or set attribute!*/ },
		//check if this observable bound to something (need to be read)
		used: function(elm) { return elm.used; },
		//check if this observable bound to input or can be changed somehow else
		boundAsUpdatable: function(elm) { return elm.boundAsUpdatable; },
		//make observable with name in container using fielddef as description
		makeElement: function(container, name, fielddef) {},
		//convert element (made with makeElement) to writable one (which is able to send itself to server)
		convertToUpdatable: function(elm) {},
		//make relation, eq rel() = X.modelBuilder.traverseRel
		// but with additionals members
		// like joins{}, val, and so on
		makeRelation: function(container, name, fielddef) {
				var c = container[name] = function() {  return X.modelBuilder.traverseRel.apply(this, arguments); }
				c.joins = {}
				c.DBFieldDef = fielddef;
				makeElement(c, 'val', fielddef); //observable = rel value (as field value)
				//TODO:
				//1) array to choose rel (if editable!) (it's like subitems = our array, but without where)
				//2) text - observable to show rel in UI (may be same as val)
				//3) tip - observable to show rel's light details
				// and maybe something to our relation bind
			},
		//TODO make observable array....

		onSendError: X.log,
		url: "/save",
		send: function(data, onresponce, onerror) {
			var p = X.XHR("POST", this.url, data)
					.then(onresponce, onerror)
					.done();
		},
		interval: 300,
		timeout: 10*1000
}
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
			var to_send = { table: qe.keyObject.DBTableDescr.DBName, 
					key: qe.keyObject.DBKeyValue,
					oid: X.OID(qe.keyObject) 
					}
			if(qe.objects) { //update/insert
				to_send.values = {}
				var hasChanges = false;
				for(var j in qe.objects) {
					var v = env.read(qe.objects[j]);
					// due to toJSON isCid translated to {cid:val} which is enough
					if(v !== objs.DBValue) //changed
						to_send.values[hasChanges = j] = v;
				}
				if(hasChanges)
					to_send_queue.push(to_send);
			} else { //delete
				if(qe.keyObject.DBKeyValue) //!DBKeyValue - new
					to_send_queue.push(to_send);
			}
		}

		var j = JSON.stringify(to_send_queue);
		sentDBQueue = DBQueue;
		DBQueue = newQueue();
		env.send(j, onresponce, onerror)
	}
	var processQueue = X.throttle(_processQueue, env.interval);
	function sendToServer(elm) {
		var n = { keyObject: elm.keyObject, objects: {} }
		{
			var last = DBQueue.cmds.slice(-1)[0];
			var qe = 
				last && n.keyObject === last.keyObject ? last
				: (DBQueue.cmds.push(n), n);
			if(qe.objects) //if not destroyed!
				qe.objects[elm.DBFieldDescr.DBName] = elm;
			if(elm.keyObject._destroy)
				qe.objects = null;
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
			}
			if(s.error)
				env.writeSaveError(c, s.error);
			else
				if(s.values) {
					//insert/update
					for(var i in s.values) {
						var obj = c.objects[i];
						if(obj) {
							obj.DBValue = s.values[i]; //from server!!!! cids translated there!!!
							if(env.read(obj) !== obj.DBValue)
								env.write(obj, obj.DBValue); //if server change value
						} else {
							//server send new value, but does't ask it to change
						}
					}
					//recalculate key for whole row
					//DBKeyValue has db values
					var k = {};
					for(var i in c.values) //loop in keyObject
						k[c[i].DBFieldDescr.DBName] = c[i].DBValue; //new DBValue here!
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
			this.DBTableDescr = DBTableDescr;
			//this.DBValue = undefined; //TODO: calc it when we read object from server, and set ready = true
			this.values = vals; //to make DBKeyValue and to trace changes in key 
			this.pended_changes = {}; // objects; pended_changes === null when ready
			this.ready = function() {
				for(var i in this.values) {
					var v = env.read(this.values[i]);
					if(X.isEmpty(v))
						return false; // has empty val -> not a pk! so, not ready
				}
				if(this.pended_changes)
					for(var i in this.pended_changes)
						X.DB.sendToServer(this.pended_changes[i]);
				this.pended_changes = null;
				return true;
			}
			this.sendToServer = function(elm) {
				if(this.pended_changes) { // save in pended changes, is key is not ready
					this.pended_changes[elm.DBFieldDescr.DBName] = elm; //unique!
				} else {
					X.DB.sendToServer(elm);
				}
			}
			//TODO: subscibe to change key
			// i.e. set ready as this.ready = KO.computed(this.ready, this)
			return this;
		},
		new_key: function(DBTableDescr, vals) { return new key(DBTableDescr, vals); }
		
		//TODO: move to prototype
		//array: 1) combo items -> filled in filter -> so in select when data come 
		// 2) subitems -> filled in select  when data come
		// one object filled in select when data come
	}
})(X.DBdefaultEnv);

/*
table def 
{
	DBName: string 
	DBFields: {
		name: {
				DBName: name
				DBType:
				DBTypeProps:
				subtype:
				cid: bool
				caption: string
				caption_combine: string

				control: html text

				min, max, re, 
				base: field ( given field(param) is a base value for this field, so it's should be greater, may be it's the same as min with expr)

				validator: function(value) {}

				strip_spaces: bool

				required: bool (means not null)
				readonly: bool (means not editable)

				master: field or expression (editable only if field not null/true)
				clear_with_master: bool (if true, clear when master clear)

				required_for: fields (this field required to set given field not null)
				readonly_if: field or expression (readonly if field not null/true)

				групповые свойства полей
				pk:pos - первичный ключ
				rk:pos - ключ для связи (нет = pk)
				unique:name.pos -уникальный ключ (соединяются по имени)
				index:name.pos -индеск (соединяются по имени)
				(нет pos - по порядку)

				visible:none/id/tip/choose/list/card/ext
					режим, где поле видимо

				cascade - 	для связей каскадное удаление и т.п.
				expanded для групп и т.п. показывать сразу открытым или нет
				tab			для групп - показывать как закладку

				кодирование связей
				name link(table.pos) "caption" ...
				если нет pos - первое поле связи
				если у таблицы есть "актуальность", 
					связь дополняется неявно true->actual
				связь идет с заданных полей на rk
				
				негенерируемые связи идут так
				name join(table) "caption" on:условие 
				в условии может быть полный select(!)
					(т.е. набор путей)
					path = val and path = val.... => строка!
					примерно так
					rel().fld = 2012
					тогда мы можем обычным образом вычислить эти условия
					в контексте целевой таблицы
					при формировании select
				т.е. если есть
				join1: T1 -> T2 on rel2().f = 2012 and cnt = T1.rid
				при обработке ноды для T2, пришедшей по join1
				мы берем rel2().f = 2012 and cnt = T1.rid
				и вычисляем его как если бы это был bind (еще один)
					и, в итоге, формируем условие для связи
					т.е. массив пар, где левая часть - observable
					а правая часть - поля или константы
					левая часть при этом, естественно, целый путь...
					и мы получаем
					T1 join T2 on T2.cnt = T1.rid and T3.f = 2012
						join T3 on T3.rid = T2.relx 
					но тут T3 еще не существует
					но мы знаем, что всю ветку T2->T3 мы добавили для join1
					(как мы это знаем? => все ноды идут под связью join1)
					T1 node ->join1-> T2 node ->relx-> T3 node
					=> когда мы собираем select мы выводим 
					(select for join1 == (T2 join T3 on T3.rid = T2.relx) )
					и потом только цепляем on
					естественно, для просто таблицы без хитростей
					мы просто пропускаем скобки вокруг просто таблицы
					'on'  ==  string
					при этом все работает как надо

				group: string
			}
			master (+clear_with_master) describe field (this) that depends from given field(param)
			required_for describe required field(s) to move record to new state, than is to set given field to not null
			readonly_if describe field that closed to edit when record come to given state (that is, given field is set to not null)
			almost always required_for = readonly_if, because we should prevent required field been changed when we in the new state
				so requred_if => readonly_if (but not vica versa)
			ss it's give pair:
			radonly_if: fields
			and required
			because if a field has conditional readonly, it's should be readonly in first time, so can't be always required
			and we can rename readonly_if as just readonly
			later,
			master:f == readonly:!f, but clear_with_master give a difference
	}
}
*/

/*
	database array
	1) destroy(obj)
	2) query(conditions, order, group)
		build sample object, bind(add to array), remove form array, makeSQL, send request, 
		wait for data, fill
	3) ready() -> bool
			check, if data come, parsed and added
	4) append()
			build sample object, bind (add to array)
			return it

*/

X.fieldDefaults = {
	//default field props = emplty! (undefiend is a good default value)
	fieldTypeDefaults: {
		//typeName : object/string
		//typeName/subtype: 
	}
}

X.processStructDef = function(globalDef, def) {
	function isRel(fld) { return fld.DBType.charAt(0) === "@"; }

	function patchAlldefs(defs) {
		//MAKE PK
		/*TODO: not here - it's nessesary only for regeneration!
		for(var i in defs) {
			var pk = [];
			var pk_x = [];
			for(var j in defs[i].DBFields) {
				if(defs[i].DBFields[j].pk)
					if(defs[i].DBFields[j].pk === true)
						pk_x.push(defs[i].DBFields[j])
					else //number pk
						pk[parseInt(defs[i].DBFields[j].pk)] = defs[i].DBFields[j];
			}
			var k = 0;
			for(var j = 0; j < pk_x.length; ++j) {
				while(pk[k]!==undefined) ++k;
				pk[k++] = pk_x[j];
			}
			var rk = [];
			var rk_x = [];
			for(var j in defs[i].DBFields) {
				if(defs[i].DBFields[j].rk)
					if(defs[i].DBFields[j].rk === true)
						rk_x.push(defs[i].DBFields[j])
					else //number pk
						rk[parseInt(defs[i].DBFields[j].rk)] = defs[i].DBFields[j];
			}
			var k = 0;
			for(var j = 0; j < rk_x.length; ++j) {
				while(rk[k]!==undefined) ++k;
				rk[k++] = rk_x[j];
			}
			if(rk.length == 0) rk = pk;
		}
		*/
		//TODO: make calculated (or simple) field to show rel (rel().text / rel().tip)
		// how to process fields?
		//	simple field: 1:1
		//	simple_rel 1:1 (val + text + tip + array for select)
		//	multifield rel
		//		rel only(!) for rel(0)
		//		other fields added as usual fields (not rels!)
		//		default ui doesn't show them

		// how to propogate rel types
		// scan all fields
		// when we found rel, go, and get target info (if it's not ready, go deeper)

		//MAKE RELS: pass 1
		for(var i in defs) {
			var tdef = defs[i];
			for(var fld_i in tdef.DBFields) {
				var fld = tdef.DBFields[fld_i];
				if( isRel(fld.DBType) ) {
						//REL(table.field_name) or, shorter REL(table) (pk[0]/rk[0])
						//for second field: REL(=rel_name.field_name)
						var mainrel = fld;
						if(fld.DBType.charAt(1)==='@') {
							//it's extended field for some rel
							// we just need to connect with primry rel field
							var relname = fld.DBType.substr(2); // skip @@
							mainrel = tdef[relname];
							if(!mainrel)
								throw "unknown rel "+relname+
									" (from "+tdef.DBName+"."+fld.DBName+")";
							fld.DBRelTarget = mainrel.DBRelTarget;
						} else {
							//it's first(primary) field for a rel
							var tname = fld.DBTypeProps.match(/^[^.]*/)[0];
							fld.DBRelTarget = defs[tname];
							if(!fld.DBRelTarget)
								throw "rel to unknown table "+tname+
									" (from "+tdef.DBName+"."+fld.DBName+")";
							if(!fld.condition)
								fld.condition = "";
							fld.DBPrimaryRelField = true;
						}
						var fn = fld.DBTypeProps.match(/\.(.*)/);
						if(fn && fn[1]) { 
							//no field name (eq REL(table.)) => 
							//we think, that it's rel without fields (all fields in condition)
						} else { // REL(teble) of REL(table.field) here
							fn = fn? fn[1] : "";
							if(!fn)
								for(var k in fld.DBRelTarget.DBFields)
									if(fld.DBRelTarget.DBFields[k].rk) //first relation key
									{	fn = fld.DBRelTarget.DBFields[k]; break; }
							if(!fn)
								for(var k in fld.DBRelTarget.DBFields)
									if(fld.DBRelTarget.DBFields[k].pk) //or first primary key
									{	fn = fld.DBRelTarget.DBFields[k]; break; }
							if(!fn)
								for(var k in fld.DBRelTarget.DBFields)
									//or first field as fallback
									{	fn = fld.DBRelTarget.DBFields[k]; break; }
							fld.DBRelTargetField = fld.DBRelTarget[fn];
							if(!fld.DBRelTargetField)
								throw "rel to unknown field "+fn+
									" (from "+tdef.DBName+"."+fld.DBName+")";
						}
						if(fld.condition && fld !== mainrel)
							mainrel.condition = mainrel.condition.Div("; ")
											+fld.condition;
						if(fld.DBRelTargetField)
							mainrel.condition = mainrel.condition.Div("; ")
											+ fld.DBRelTargetField.DBName 
											+":"+ fld.DBName;
							
						//TODO: text/tip

						// how we can make UI for this kind of rels?

						// default UI:
						//		execute select, get field val
						//		set them to record, set visual fields alase
						//	so, choose should have fields + text + tip
						//		and all(!) fields under rel or rel dependent
						// to build this choose we can collect choose UI (or id UI)
						// + text + tip (we know them from table)
						// +(!) all used fields under rel, rebased to choose table
						// after that choose can be implemented in the client

						// in general situation, we know target, so we know choose UI
						// and we know adv. filter from generalized rel
						// in this filter we have 1) constants 2) upper level fields
						// (as in short form of rel)
						// so, genralized rel differs 
						// from usual one on due to it contains constants in join
						// and, maybe, some container field used not a first level
						// anyweay, we have set of criterias fiel_paths = context
						// and, so, we can referse them when user make choose
						// and set context's fields

						// so, it's enough to have only a condition set 
						//  to be able make UI for rel choosing
						// because 1) conditions say which fields we should assing in container
						// 2) container know which fields are under rel
						// 3) target know UI fields
					}
				}
			}
		}
		//MAKE RELS: pass 2
		var walkRelPath = function(rel) {
			if( rel.DBRelTargetField && isRel(rel.DBRelTargetField.DBType) ) //not processed yet 
				//process only rels wich has target field and leave general rel unchanged
				walkRelPath(rel.DBRelTargetField)
			rel.DBType = rel.DBRelTargetField.DBType;
			rel.DBTypeProps = rel.DBRelTargetField.DBTypeProps;
			X.defaults(rel, rel.DBRelTargetField); //copy other (usuallt undefined) props
		}
		for(var i in defs) {
			var tdef = defs[i];
			for(var fld_i in tdef.DBFields) {
				var fld = tdef.DBFields[fld_i];
				if(fld.DBRelTargetField && isRel(rel.DBRelTargetField.DBType))
					walkRelPath(fld);
			}
		}

		//MAKE DEFAULTS
		for(var i in defs) {
			var tdef = defs[i];
			for(var fld_i in tdef.DBFields) {
				var fld = tdef.DBFields[fld_i];
				X.defaults(fld, X.fieldDefaults.fieldTypeDefaults[fld.DBType+'/'+DBSubtype]);
				X.defaults(fld, X.fieldDefaults.fieldTypeDefaults[fld.DBType]);
			}
		}
	}

	var a = typeof def === "string"? def.split('\n') : def;
	var tdef = null;
	var fields = null;
	var currentField = null;
	for(var i = 0; i < a.length; ++i)
	{
		if(/^\s*(#|$)/.test(a)) continue; //skip empty
		var m;
		if(m = a.match(/^\s*TABLE\s+(.*)/i)) { //TABLE name caption props
			if(!(m = m[1].match(/^([a-z_][a-z0-9_]*)\s+(.*)/i))) 
				throw "bad tabledef in line: "+i;
			tdef = m[2].parseXON("caption");
			tdef.DBName = m[1];
			tdef.DBFields = {};
			globalDef[tdef.DBName] = tdef; // now it's current table
			fields = null;
			continue;
		}
		if(/^\s*FIELDS:\s*/i.test(a)) {
			if(fields)
				throw "unexpected 'fields' marker in line: "+i;
			fields = tdef.DBFields;
			currentField = null;
			var ident = "";
			continue;
		}
		if(/^\s*DATA:\s*/i.test(a)) {
			// data marker
			continue;
		}
		// field def or table props here
		if(fields) { //field def
			if(currentField && a.beginsWith(ident) && /^\s+/.test(a.substr(ident.length))) {
				//continue field
				X.mixin(a.parseXON(), currentField);
			} else {
				if(!(m = 
				a.match(/^(\s*)([a-zA-Z_][a-zA-Z_0-9]*)\s+([a-z]+)(\([^)]+\))?\s+(.*)/i)
				))
					throw "bad field definition in line: "+i;
				ident = m[1];
				fields[currentField.DBName = m[2]] = currentField = m[5].parseXON("short_caption");
				currentField.DBTable = tdef;
				currentField.DBType = m[3].toUpperCase;
				currentField.DBTypeProps = m[4] && m[4].replace(/\s/g,"");
				if(!currentField.caption) 
					currentField.caption = currentField.DBName;
				if(m = currentField.caption.match(/^(.*?)\/\/(.*)$/)) {
					currentField.caption = m[1];
					currentField.caption_combine = m[2];
				}
				if(!currentField.caption_combine) 
					currentField.caption_combine = currentField.caption+"▹<$>";
				if("REL") {
					//currentField.DBTypeProps === target here
					//or target.field
				}
				if("ITEMS") {
					//currentField.DBTypeProps === source + filter confitions
				}
			}
		} else { //table props
			X.mixin(a.parseXON(), tdef);
			continue;
		}
	}
}
/*
	параметрические связи 
	.info('tel')
	.tel = function() { return this.info('tel').value }
	.data(year) ===> данные года
	.current_data() ===> данные текущего года

	тут еще нужно уметь переименовывать поля, т.е.
		.tel дает .info('tel').value === 'telephone'.'value'
		а нам надо - 'telephone'
		но в нашем случае тут сработает простое переименование связи, где одно поле
			(как в обычном справочнике)
			(т.е. в справочнике могут быть поля, не добовляемые под связь)
			(т.е. самоописанные)
			container+name=>value - самоописанный
		у самоописанных полей мы рисуем заголовок только в форме собственной таблицы
		или в отчет собственной таблицы, если его все равно рисовать
	а остальные можно и не переименовывать
		только иногда "собственный" заголовок связи надо пропускать вообще (пустой!!!)
			это если он только для 1:1

	формирование заголовков
	надо
		1) краткий заголовк (caption)
		2) длинный заголовок (long_caption)
		3) формирование полного заголовка по краткому по связям
			задается правилом
			в английском это может быть %child of %parent
			в русском %child %parent в родительном падеже
		т.е. нужен 4
		4) childs_caption, который содержит символ %, куда подставляется child
		5) для главной связи (город -> страна) мы не ставим child_caption
			т.е. надо чтобы было так
			проставшик - орг-я - город - страна
			в городе
				"название" (соственное название города)
				"код" (соственный код города)
				"название страны"
				"код страны"
			в орг-и 
				1 "название"
				2 "город" (а не "название города")
				3 "код города"
				4 "страна"
				5 "код страны"
				 (а не "код страны города")
			при этом
				2 - это через "самоописанное поле"
					(при этом родительный падеж, конечно, не подставляется)
					(аналогично - если связь выносится не по полям, а целиком)
				5 - видимо, страна - это mainrel для города
					("естественная принадлежность")
					при этом мы можем и фильтровать хорошо
					и не использовать доп. заголовок сверху

	когда нужен long_caption:
	1) поле в форме/отчете подписывается как можно короче (short)
	2) поле из под связи имеет заголовок связи (short!)
	3) поле в группе подписывается 
		в группе в форме/отчете коротк (т.к. группа рядом)
		а в списке полей - длинно, т.к. там группы не видно
		т.о. это для групп
	но для группы полезно
		а) иногда вообще не трогать названия
		б) как-то добавлять себя (это аналогично связи! там же тоже группа)
	т.е. заголовки надо просто комбинировать
	grp combine with elem
форма
	Председатель
		Фамилия
		Должность
отчет
	  Председатель
	Фамилия Должость
меню
	Фамилия председателя
	Должность председателя
*/

X.modelBuilder = (function(env) {
	var makeAlias = function(n) {
			return "abcderfghijklmnopqrstuvwxyz".charAt(n) ||
				makeAlias(n/10)+(n%10);
		}
	var res =
	{
		tableNode: function(table, parent, rel) {
			//TODO: this function should be genegrates once (in parsing)
			// and later we call it to build new models when we need
			// it looks like new descr.TableName;

			this.table = table;
			this.alias = "";
			this.condition = null;
			this.keyObject = null;

			//TODO: fill fields and rels here
			// in each rel fill joins = {}
			// and DBRelTarget 
			//this.fields
			//+elm.DBFieldDescr = DBFieldDescr;
			//and 
			//elm.destroyElement = function(to_destroy) { 
//				this.destroy(to_destroy);
	//			to_destroy.keyObject._destroy = true;
		//		to_destroy.sendToServer();
			//}

			return this;
		},
		traverseRel: function(rel, rel_arguments) {
			var key = Array.prototype.join.call(rel_arguments,":") || "";
			return rel.joins[key] ||
				(rel.joins[key] = new X.modelBuilder.tableNode(
					rel.DBFieldDescr.DBRelTarget //table linked with rel
					,rel.parent //source node (not a table!)
					,rel //rel itself
				))                ;
		},
		//x.rel() == X.modelBuilder.traverseRel(this.rel, arguments)


		makeConditionJoins: function(root, cond, context, rel_params) {
			if(cond.cache && cond.cache[rel_params]) return cond.cache[rel_params]; //cached
			//parse cond
			var real_expr = [];
			var re = /\s*([^:]+):\s*([^ ;]*)\s*;?/g;
			var m;
			while(m=re.exec(cond)) {
				var fld = m[1];
				var val = m[2];
				var fld = eval("root."+fld); //eval path in root context (as KO)
				var fld = { table: fld.parent, field:fld.DBFieldDescr}
				real_expr.push({ fld: fld, val: val}); 
					//TODO: find appropriate table in context stack
					// it's changed from call to call so can't be cached - what we should do?
					//BUT!
					// we aways has condition in from field1 = const or field1 = field2
					// where field1 is from right table and field2 from context table (which is ole level up)
					// at least for now it's always so
					// so! we can just bound field2 to alias later, in sql
			}
			params = rel_params && rel_params.split(':');
			if(params) {
				var p = 0;
				for(var i = 0; i < real_expr.length; ++i)
					if(real_expr[i].val === '?')
						real_expr[i].val = params[p++];
			}
			cond.cache = cond.cache || {};
			return cond.cache[rel_params] = real_expr;
		},
		makeRelsAndAliases: function(table_node, alias) {
			alias = alias || { current: 0};

			table_node.alias = makeAlias(alias.current++);

			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) { //it's rel params
						var target = cur.joins[j];
						target.condition = 
							X.modelBuilder.makeConditionJoins(target, rel.DBFieldDescr.condition, table_node, j);
						this.makeRelsAndAliases(target, alias);
					}
				}
			}
		},
		//collectJoins return table_node, it it doesnt have joins or array of joined tables
		// with tablenode as first element
		//table_node or first element of array contains join condition for outer node
		collectJoins: function(table_node) {
			//here we have condition for this node
			//we should right(!) associate joins under relations
			// T1->T2->T3
			// T1 left join (T2 left join T3 on T3.rid = T2.rel) on T2.rid=T1.rel
			// it's returned as [T1:null [T2(T2.rid=T1.rel) T3(T3.rid = T2.rel)]]
			
			var ret = [];
			for(var i in table_node) {
				var rel = table_node[i];
				if(rel && rel.joins) { //it's rel
					for(var j in rel.joins) { //it's rel params
						ret.push(this.collectJoins(rel.joins[j]));
					}
				}
			}
			
			if(ret == []) {
				//no subjoins -> return table as it is
				return table_node;
			}
			ret.unshift(table_node); // if we have joins, add table to first element in join sequence
			return ret; 
		},
		generateKeyObject: function(table_node) {
			if(table_node.keyObject) return table_node.keyObject;
			var kv = {}
			for(var i in table_node) {
				var elem = table_node[i];
				if(elem && elem.DBFieldDescr.pk)
					kv[elem.DBFieldDescr.DBName] = elem;
				if(elem && elem.val && elem.val.DBFieldDescr.pk)
					kv[elem.val.DBFieldDescr.DBName] = elem.val;
			}
			return table_node.keyObject = X.DB.new_key(table_node.table, kv);
			//TODO: subscibe to changes
		},
		makeUpdatables: function(table_node) {
			for(var i in table_node) {
				var elem = table_node[i];
				elem = elem && elem.val || elem;
				if(elem && env.boundAsUpdatable(elem)) {
					elem.keyObject = this.generateKeyObject(table_node);
					elem.sendToServer = function() { this.keyObject.sendToServer(this); }
					env.convertToUpdatable(elem);
					//TODO: subscibe to changes
				}
				
				var rel = elem;
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
				if(elem && env.used(elem)) used.push({node: table_node, elem: elem});
				var rel = elem;
				if(rel && rel.joins) { //it's rel
					for(var j in rel)
						if(rel[j] && env.used(rel[j]))
							used.push({node: table_node, elem: rel[j]});
					for(var j in rel.joins)
						this.collectUsage(rel.joins[j], usage);
				}
			}
			
		},
		makeSQL: function(table_node) {
			this.makeUpdatables(table_node);
			this.makeRelsAndAliases(table_node);

			var used = [];
			this.collectUsage(table_node, used);
			var joins = this.collectJoins(table_node);

			return {
				joins: joins, //FROM
				used: used //SELECT
			}
		},
		sqlToJSON: function(sql_object) {
			var select = [];
			for(var i = 0; i < sql_object.used; ++i)
				select.push(sql_object[i].node.alias+"."+sql_object[i].elem.DBFieldDescr.name)
			
			var recf = function(joins) {
				if(!joins.length) // simple table
					return { table: joins.DBTableDescr.name, 
							alias: joins.alias, 
							on: joins.condition //it's array, ready to send
						}
				//multijoin
				var res = []
				for(var i = 0; i < joins.length; ++i)
					res.push(recf(joins[i]));
				return res;
			}
			var from = recf(sql_object.joins);
			return { select: select, from: from };
		}
	}
	return res;
})();

/*
	var DBQueue - то, что изменялось со времени последней посылки
	var sentDBQueue - посланные объекты, обновления которых мы ждем
*/
/*
	свойства поля
		DBValue - значение, полученное из базы
		read() - то, что сейчас в объекте // e()
		write() - записать в объект       // e(value)
		DBDescr - общий дескриптор поля
			pk:n - позиция в ключе
			DBName - имя в базе
			table - общий дескриптор таблицы (обычно container)

	свойства объекта (строки)
		table - ссылка на корневой табличный объект (обычно container)
		DBValue{} - ключ в базе, для новых - пусто
		DBDescr - общий дескриптор таблицы
			DBName - имя в базе

	для table (array)
		oldContent{} - OIDs объектов в массиве -> сами объекты
		DBDescr - общий дескриптор таблицы
			DBName - имя в базе
*/
/*
	формирование select
	1) для объкта просто вычисляем поля, которые забиндились
	2) если есть DBValue - выполняем select (т.к. есть что читать)
		нет DBValue - можно ничего и не делать (пустая запись)
	3) DBValue = { имя: значение } для объекта
	4) для массива мы добавляем фиктивный объект (скрытый!)
		с пустым ключом
		и выполняем определение для него
		потом мы учитываем фильтр и формируем запрос
	кто разбирает пути???
	а) клиент - проще кодировать
	б) сервер - надежнее?
	сервер добавляет код проверки чтения/записи таблиц (условия)
	но по связи мы не проверяем общее условие, только частные условия полей
	(они не входят обычно в ключ)
	если клиент может задать свои связи, там могут быть свои таблицы
	клиенту надо задавать свои связи в построителе, но это явно видно и тут код другой

	итак, если разбирает клиент, сервер может построить доп. условия
		(но не может пропустить условия на связь)
	если разбирает сервер, он все может, но это завязывает на конкретные
*/


/*
SQL SELECT => json:

select :: = 
{
	from: [
		table or select
	],
	where: [ //OPT
		where_cond
	],
	order: [ //OPT
		field exprs or DESC(field expr)
	],
	group: [ //OPT
		field exprs
	],
	select: [
		array of field exprs
	]
}

table ::= 
{
	table: id, 
	aliad: id,
	inner: true/false, //OPT 
	joinON: join condition on key
}

join condition on key ::= 
{
	filed_name: value in join
	//field names only from keys
}

value in join ::=
	filed name in upper
	table.field name in upper (in specific nearest table) (на сервер надо посылать точное значение с псевдонимом)
	'const' - text constant
	digis - number constant
	outer.field (in outer select) //may be outer.outer.filed and so on


where_cond ::=
{
	field expr : where value
	+
	op: =, !=, <, >, <=, >=, LIKE, NOT LIKE, IN SELECT, NOT IN SELECT
}
or array of where conds (they connected with 'or')

where value = 
	null
	'const'
	digits
	array of where values
	select

field_expr ::= fixed grammar for simple expr (обратная польская запись + сервер знает все! ф-и)

в принципе, сервер может собирать выражения из обратной польской записи
	тут есть проверки полей и проверки функций
но можно и просто разбирать по регулярным выражениям
	тут примерно такое же выражение (но нужны только имена полей и функций)
	при этом сервер вообще плюет на синтаксис!
	но доступ к полям все равно есть
	и есть доступ функциям
	и они отличаются
	тут могут быть соотв-но только разрешенные ф-и
	и только разрешенные поля (из таблиц)
т.е. парсер простой!


*/
