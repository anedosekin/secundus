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
		sendQuery: function(model) {
			if(model.sendQuery) {
				model.sendQuery();
			}
			else {
				for(var i in model) {
					if(model[i] && model[i].sendQuery) model[i].sendQuery();
				}
			}
		}
	}
})(X.DBdefaultEnv);