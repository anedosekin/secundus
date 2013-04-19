(function() {
	var init = ko.bindingHandlers['value']['init'];
	ko.bindingHandlers['value']['init'] = function(element, valueAccessor, allBindingsAccessor) {
		init.apply(this, arguments);
		var value = valueAccessor();
		value.boundAsUpdatable = true;
	}
})();
(function() {
	var init = ko.bindingHandlers['selectedOptions']['init'];
	ko.bindingHandlers['selectedOptions']['init'] = function(element, valueAccessor, allBindingsAccessor) {
		init.apply(this, arguments);
		var value = valueAccessor();
		value.boundAsUpdatable = true;
	}
})();