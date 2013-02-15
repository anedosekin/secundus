/* 
core
*/
if(!String.prototype.trim) String.prototype.trim = function () { return this.replace(/^\s+|\s+$/g,''); }
if(!String.prototype.trimLeft) String.prototype.trimLeft = function () { return this.replace(/^\s+/,''); };
if(!String.prototype.trimRight) String.prototype.trimRight = function () { return this.replace(/\s+$/,''); };
var X = {};
X.isFunction = function(obj) { return typeof obj === 'function'; }
X.isObject = function(obj) { return obj === Object(obj); }
X.isArray = Array.isArray || function(obj) { return toString.call(obj) == '[object Array]'; }
X.isEmpty = function(val) { return val === undefined || val === null || val === ""; }
/*
ko plugin
*/
/*html*/
var sko_html = {
relation:'<div class=relation><input type=hidden data-bind=\'value:$rel.value\'><input data-bind=\'value: $rel.text, valueUpdate: "afterkeydown", event:{keydown:$rel.acceptItem, blur:$rel.lostFocus}\'/><table data-bind=\'visible: $rel.text && $rel.inputChanged, event:{mousedown:$rel.gotFocus}\' cellspacing="0" cellpadding="0" border="0"><thead><tr data-bind=\'foreach:$rel.tableHead, visible:$rel.tableRows().length && $rel.tableRows()[0].length>2\'><th><span data-bind=\'text:$data\'></span></th></thead><tbody data-bind=\'foreach:$rel.tableRows\'><tr class=tablerow data-bind=\'click: $rel.chooseItem, css:{ current: $index()==$rel.currentIndex() }, foreach:$data\'><td data-bind=\'text:$data\'></tr></tbody></table></div>'
}
function RelationExtend(relation) {
	var self = this;
	self.$rel = {
		relData: ko.utils.unwrapObservable(relation.data),
		relHeader: ko.utils.unwrapObservable(relation.header),
		hideEmpty: ko.utils.unwrapObservable(relation.hideempty),
		value: relation.value,
		text: ko.observable(''),
		inputChanged: ko.observable(false),
		currentIndex: ko.observable(-1),
		focusInsideTable: ko.observable(false),
		gotFocus: function() { self.$rel.focusInsideTable(true) },
		lostFocus: function() { if(!self.$rel.focusInsideTable()) self.$rel.inputChanged(false) }
	}
	self.lostFocus = 
	self.$rel.text.subscribe(function() {
		self.$rel.inputChanged(true);
		self.$rel.currentIndex(-1);
	});
	self.$rel.hiddenColumns = function() {
		var hidden = [];
		var data = self.$rel.relData;
		if(data.length) {
			for(var j = 0; j < data[0].length;j++) {
				var i = 0;
				while(i < data.length && !data[i][j]) i++;
				hidden.push(i == data.length && self.$rel.hideEmpty);
			}
		}
		return hidden;
	}();
	self.$rel.tableRows = ko.computed(function() {
		var text = ko.utils.unwrapObservable(self.$rel.text);
		var filtered = [];
		var hidden = self.$rel.hiddenColumns;
		var data = self.$rel.relData;
		for (var i = 0; i < data.length; i++) {
			for(var j = 0;j < data[i].length;j++) {
				if(text && data[i][j].indexOf(text.trim())===0) {
					var line = [];
					for(var k = 0;k < data[i].length; k++) {
						if(!hidden[k]) line.push(data[i][k]);
					}
					filtered.push(line);//фильтрация по всем полям
					break;
				}
			}
		}
		return filtered;
	}).extend({ throttle: 500 });//задержка вычисления мс
	
	self.$rel.tableHead = ko.computed(function() {
		var header = [];
		var hidden = self.$rel.hiddenColumns;
		var head = self.$rel.relHeader;
		for (var i = 0; i < head.length; i++) {
			if(!hidden[i]) header.push(head[i]);
		}
		return header;
	});
	//Behavior
	self.$rel.chooseItem = function(item) {
		self.$rel.value(item[0]);
		self.$rel.text(item.join(' '));
		self.$rel.inputChanged(false);
		self.$rel.focusInsideTable(false);
	}
	
	self.$rel.acceptItem = function(data,event) {//38-up 40-down
		var rows = self.$rel.tableRows();
		var index = self.$rel.currentIndex;
		switch(event.keyCode) {
		case 13:
			if(rows.length == 1) 
				index(0);
			if(index()>=0) {
				self.$rel.value(rows[index()][0]);
				self.$rel.text(rows[index()].join(' '));
				self.$rel.inputChanged(false);
			}
			break;
		case 38://up
			{
				var idx = index();
				var rows = rows.length;
				if(rows>0 && idx>0)
					index(idx-1);
				break;
			}
		case 40://down
			{
				var idx = index();
				var length = rows.length;
				if(length>0 && idx<(length-1)) 
					index(idx+1);
				break;
			}
		}
		return true;//для действия по умолчанию
	}
}
/* Usage:
	<div data-bind="relation:{ value: relVal, data: tableData, header: tableHead, hideempty: true }"></div>
	header: array of table header captions
	data: array of table rows. Number of elements in each array is equal to number of elements in header array
	value: observable which will be set with choosen value
	hideempty: if set to true will remove empty columns from table
*/
ko.bindingHandlers['relation'] = {
	'init':
	function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
		var innerBindingContext = bindingContext.extend(RelationExtend(valueAccessor()));
		ko.utils.setHtml(element,sko_html.relation);
		ko.applyBindingsToDescendants(innerBindingContext, element);
		return { controlsDescendantBindings: true };
	}
}
/*
normalizer
*/
ko.extenders.trim = function(target, option) {
	var result = ko.computed(
	{
		read: target,
		write: function(newValue) {
			if(option.toLowerCase() == 'edge') {
				target(newValue.trim());
			} else
			if(option.toLowerCase() == 'all') {
				target(newValue.replace(/\s+/g,''));
			} else
			if(option.toLowerCase() == 'normal') {
				target(newValue.replace(/^\s+|\s+$/g,'').replace(/\s+/g,' '));
			} else {
				target(newValue);
			}
		}
	});
	result(target());
	return result;
}
/*
 usage tracing
*/
function usageTrace( model ) {
	for(var i in model) {
		console.log(i+' used '+(model[i].getSubscriptionsCount && model[i].getSubscriptionsCount()));
	}
}
/*
ko.validation plugin
*/
/*
ko.validation.rules['mustEqual'] = {
	validator: function (val, otherVal) {
		return val === otherVal;
	},
	message: 'The field must equal {0}'
};
ko.validation.registerExtenders();

//the value '5' is the second arg ('otherVal') that is passed to the validator
var myCustomObj = ko.observable().extend({ mustEqual: 5 });
*/