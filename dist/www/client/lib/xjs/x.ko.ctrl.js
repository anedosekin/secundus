var ko_ctrl_html = '<span data-bind="text:elm.$.caption"></span><span data-bind="text:elm"></span>'
function CtrlExtend(element) {
	var self = this;
	self.elm = element;
}
ko.bindingHandlers['ctrl'] = {
	'init':
	function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
		var innerBindingContext = bindingContext.extend(new CtrlExtend(valueAccessor()));
		ko.utils.setHtml(element, ko_ctrl_html);
		ko.applyBindingsToDescendants(innerBindingContext, element);
		return { controlsDescendantBindings: true };
	}
}
/*
<span data-bind="caption:rel"></span>
<span data-bind="relation:rel"></span>
->
1. make updatables for city_id
2. <span data-bind="click: Z.choose.bind($data, city_id)"></span>


<input type=hidden data-bind="value:rel().value">
<input type=hidden data-bind="value:fld">
<span data-bind="relation:city_id"></span>

*/
X.firstchild = function(n)
{
	x=n.firstChild;
	while (x.nodeType!=1)
	{
	x=x.nextSibling;
	}
	return x;
}
var Z = (function(env) {
	ko.bindingHandlers['use'] = {
		'init':
		function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
			var value = valueAccessor();
			if(X.isArray(value)) {
				for(var i in value)
					env.makeUse(value[i]);
			}
			else
				env.makeUse(value);
		}
	}
	ko.bindingHandlers['updatable'] = {
		'init':
		function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
			//TODO:safe writing for valueAccessor (unwrapObservable etc.)
			var value = valueAccessor();
			if(X.isArray(value)) {
				for(var i in value) {
					env.makeUse(value[i])
					value[i].boundAsUpdatable = true;
				}
			} else {
				env.makeUse(value)
				value.boundAsUpdatable = true;
			}
		}
	}
	//internally registers it's own click method
	ko.bindingHandlers['openform'] = {
		'init':
		function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
			var params = allBindingsAccessor();
			//params["modal"]
			//params["inline"]
			//register click
			bindingContext["$data"].selectableNode = true;//table_node
			var formTemplate = document.querySelector( valueAccessor() );
			var openform = function(data, e) {
				if(!Z.insideControl(element, e.srcElement || e.target))
					Z.openform(
						X.firstchild(element) || element, formTemplate, bindingContext);
			}
			var click = function () { return { 'click' : openform } };
			ko.bindingHandlers['event']['init'].call(this, element, click, allBindingsAccessor, viewModel);
		}
	}
	ko.bindingHandlers['choose'] = {
		'init':
		function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
			var rel_node = valueAccessor();
			element.setAttribute("ctrl", 1)
			rel_node.element().boundAsUpdatable = true;
			var context = bindingContext.createChildContext( rel_node )
			var $ = Z.add_service( context );
			$.show = function() {//only after applyBindings is executed
				$[rel_node.$$.name].sendSelect();
				$.choosing(true);
			}
			$.select = function(table_node) {
				Z.choose( rel_node, table_node );
				$.hide();
			}
			$.hide = function() {
				$.choosing(false);
			}
			$.root = rel_node.element().root;
			$.choosing = ko.observable(false);
			env.makeArray($, rel_node.$$.name, rel_node.$$)
			ko.applyBindingsToDescendants(context, element);
			return { controlsDescendantBindings: true };
		}
	}
	var rez = {
		choose: function(rel_node, source_node) {
			//TODO:choose multiple values
			//rel_node is relation node in current table
			//source_node - node with choosen vals
			//update old values to null
			//update new values to old
			var rel = rel_node.element();//relation to choose - object
			if(rel.filter.parametrized) {
				var ff = rel.filter.get_flds();
				var raw = rel.filter.get_raw_vals( rel_node.rel_params );
				X.Q.sync.lock();//two different objects - so keys isnt equal - and queries dont stack
				for(var i in ff) {
					env.write( env.oko( ff[i] ) , "")
				}
				for(var i in ff) {
					var fld = source_node[ i ]
					env.write(env.oko( fld ), raw[i] )
				}
				X.Q.sync.unlock();
			} else {
				var vals = rel.filter.get_vals();
				for(var i in vals) {
					var src_fld = source_node[ i ]
					env.write(env.oko( vals[i] ), env.read( src_fld ) );
				}
			}
		},
		openform: function(target, template, bindingContext) {
			if(target.formOpened) 
				return;
			//target - element, where to attach form
			//ko_context - knockout bindingContext
			//table_node of record to show
			var table_node = bindingContext["$data"];
			var form = document.createElement('DIV');
			form.style.position = "absolute";
			form.innerHTML = template.innerHTML;
			target.appendChild(form);
			
			var context = bindingContext.createChildContext( table_node )
			var $ = Z.add_service( context );
			$.close = function() {
				target.removeChild(form);
				target.formOpened = false;
			}
			target.formOpened = true;
			ko.applyBindingsToDescendants(context, form);
			X.modelBuilder.markupForUpdates( table_node.element(), table_node );
			if(table_node.key.ready()) {
				var s = X.sql.makeSelect( table_node, table_node.key.current() );
				s && X.Q.async.send( s.sql, s.dist );
			}
		},
		insideControl: function( cont, elem ) {
			//elem - element-border
			//target - element to check
			var tagRestrict = {
				"input":true,
				"button":true,
				"select":true,
				"textarea":true,
			}
			do {
				//control is inside itself
				if( tagRestrict[ elem.tagName.toLowerCase() ] || elem.getAttribute('ctrl')!=null )
					return true;
			}
			while(elem !== cont && (elem = elem.parentNode) )
			return false
		},
		add_service: function( context ) {
			return ko.utils.extend(context, { $:{} } ).$;
		},
		extend:function(elem, params) {
			elem.extend = elem.extend || [];
			elem.extend.push(params);
		},
		context : function(bindname, patch) {
			//patch(dom, value, binds)
			ko.bindingHandlers[bindname] = {
				'init':
				function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
					var value = valueAccessor();
					var context = bindingContext.createChildContext(value);
					element = patch.call(context, element, allBindingsAccessor()) || element;
					ko.applyBindingsToDescendants(context, element);
					return { controlsDescendantBindings: true };
				}
			}
		}
	}
	return rez;
})(X.DBdefaultEnv);
