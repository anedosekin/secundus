X.runModel = (function(env) {
	return {
		single: function(tableObject) {
			return env.makeRecord(tableObject);
		},
		multi: function(tableObject) {
			var model = {};
			env.makeArray(model, tableObject.name, tableObject);
			return model;
		},
		sendSelect: function(model) {
			if(model.sendSelect) {
				model.sendSelect();
			}
			else {
				for(var i in model) {
					if(model[i] && model[i].sendSelect) model[i].sendSelect();
				}
			}
		}
	}
})(X.DBdefaultEnv);