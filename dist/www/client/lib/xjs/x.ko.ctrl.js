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
				for(var i=0;i<value.length;++i)
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
				for(var i=0;i<value.length;++i) {
					env.makeUse(value[i])
					env.makeUpdatable(value[i]);
				}
			} else {
				env.makeUse(value)
				env.makeUpdatable(value);
			}
		}
	}
	//internally registers it's own click method
	ko.bindingHandlers['openform'] = {
		'init':
		function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
			var formTemplate = document.querySelector( valueAccessor() );
			var params = allBindingsAccessor();
			//params["modal"]
			//params["inline"]
			//register click
			var openform = function() {
				if(element.formOpened) return;
				var target = X.firstchild(element) || element;
				var form = document.createElement('DIV');
				form.style.position = "absolute";
				form.innerHTML = formTemplate.innerHTML;
				target.appendChild(form);
				
				var context = bindingContext.extend({
					close: function() {
						target.removeChild(form);
						element.formOpened = false;
					}
				});
				
				element.formOpened = true;
				ko.applyBindingsToDescendants(context, form);
				X.Select.sendSelect({ target:context["$data"], node: context["$data"]});
			}
			var click = function () { return {'click' : openform } };
			ko.bindingHandlers['event']['init'].call(this, element, click, allBindingsAccessor, viewModel);
		}
	}
	var rez = {
		updatable: function(elm) {//TO env
			if(elm.joins) {
				if(!elm.joins[""]) 
					elm.call(elm.parent);//make relation usable
				var node = elm.joins[""];//rels with no parameters
				var ops = node.linkops
				for(var i = 0;i < ops.length;++i)
					env.makeUpdatable( env.oko( ops[i].value || ops[i].field));
			} else
				env.makeUpdatable(elm);
		},
		choose: function(rel, source_node) {
			//TODO:choose multiple values
			//rel is relation to choose - object
			//source_node - node with choosen vals
			var node = rel.joins[""];//rels with no parameters
			var ops = node.linkops;
			//elems, $data, mouse event
			//update old values to null
			//update new values to old
			if(ops.valuable) {
				X.Upserte.lockSending();
				for(var i = 0;i<ops.length;++i) {
					var fld = ops[i].field;
					if(ops[i].rawvalue)
						env.write(env.oko(fld), '');
				}
				for(var i = 0;i<ops.length;++i) {
					var fld = ops[i].field;
					if(ops[i].rawvalue)
						env.write(env.oko(source_node[fld.$.name]), ops[i].rawvalue)
				}
				X.Upserte.unlockSending();
			} else {
				X.Upserte.lockSending();
				for(var i=0;i<ops.length;++i) {
					var fld = ops[i].field;
					var val = ops[i].value;
					env.write(env.oko(val), env.read(source_node[fld.$.name]));//dyssync
				}
				X.Upserte.unlockSending();
			}
			
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
