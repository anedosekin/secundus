var ko_ctrl_html = '<span data-bind="text:elm.$.caption"></span><span data-bind="text:elm"></span>'
function CtrlExtend(element) {
	var self = this;
	self.elm = element;
}
ko.bindingHandlers['ctrl'] = {
	'init':
	function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
		var innerBindingContext = bindingContext.extend(CtrlExtend(valueAccessor()));
		ko.utils.setHtml(element, ko_ctrl_html);
		ko.applyBindingsToDescendants(innerBindingContext, element);
		return { controlsDescendantBindings: true };
	}
}