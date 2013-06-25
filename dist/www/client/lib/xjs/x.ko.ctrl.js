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
function processTemplate() {
	

}
function processModel() {
	
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
	var rez = {
		updatable: function(elm) {//TO env
			if(elm.joins) {
				var node = elm.joins[""];//rels with no parameters
				var ops = node.linkops
				for(var i = 0;i < ops.length;++i)
					env.makeUpdatable( env.oko( ops[i].value || ops[i].field));
			} else
				env.makeUpdatable(elm);
		},
		choose: function(rel, source_node) {
			//rel is relation to choose - object
			//source_node - node with choosen vals
			var node = rel.joins[""];//rels with no parameters
			var ops = node.linkops;
			//elems, $data, mouse event
			//update old values to null
			//update new values to old
			if(ops.valuable) {
				X.Select.lockSending();
				X.Upserte.lockSending();
				for(var i = 0;i<ops.length;++i) {
					var fld = ops[i].field;
					if(ops[i].rawvalue)
						env.write(env.oko(fld), '');//dyssync
				}
				X.Upserte.unlockSending();
				X.Upserte.lockSending();
				for(var i = 0;i<ops.length;++i) {
					var fld = ops[i].field;
					if(ops[i].rawvalue)
						env.write(env.oko(source_node[fld.$.name]), ops[i].rawvalue)
				}
				X.Upserte.unlockSending();
				X.Select.unlockSending();
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
		binding : function(bindname, patch) {
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
