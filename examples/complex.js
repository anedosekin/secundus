function patchModel(model) {
		for(var i in model) {
			var table = model[i];
			table.name = i;
			for(var j in table) {
				var field = table[j];
				field.name = j;
				if(field.target) {
					field.target = model[field.target];
				}
			}
		}
		return model;
	}
var metaModel = patchModel({
	Streets:{
		id:{caption:'id', pk:true},
		city:{caption:'Город',
			target:'Cities',
			condition:[{there:'city_name',here:'city'}]
		}
	},
	Cities:{
		id:{caption:'id', pk:true},
		city_name:{caption:'Название'},
		capital:{caption:'Столичный город'},
		population:{caption:'Население'},
		country:{caption:'Страна',
			target:'Countries', 
			condition:[{there:'country_name',here:'country'}]
		},
		persons_in_city:{caption:'Жители',
			target:'Persons',
			condition:[{there:'birthcity', here:'id'}],
			array:'auto'
		}
	},
	Countries:{
		country_name:{caption:'Название', pk:true},
		capital_city:{caption:'Столица',
			target:'Cities',
			condition:[{there:'capital',value:'true'}, {there:'country',here:'country_name'}]
		},
		cities:{caption:'Города',
			target:'Cities',
			condition:[{there:'country', here:'country_name'}],
			array:'auto'
		}
	},
	Persons:{
		id:{caption:'id', pk:true},
		first_name:{caption:'Имя'},
		second_name:{caption:'Отчество'},
		surname:{caption:'Фамилия'},
		birthdate:{caption:'Дата рождения'},
		birthcity:{caption:'Место рождения',
			target:'Cities',
			condition:[{there:'id',here:'birthcity'}]
		}
	},
	Organisations:{
		id:{caption:'id', pk:true},
		full_name:{caption:'Полное название'},
		short_name:{caption:'Краткое название'},
		banks_in_organizations:{caption:'Банки среди организаций',
			target:'Banks',
			condition:[{there:'organization', here:'id'}],
			array:'auto'
		},
		saler_in_organizations:{caption:'Продажи организации',
			target:'Sales',
			condition:[{there:'saler', here:'id'}],
			array:'auto'
		}
	},/*
	Identity:{
		identification:{
			dependent:'type',
			'person':{
				surname:{caption:'Фамилия'},
				second_name:{caption:'Отчество'},
				first_name:{caption:'Имя'}
			},
			'organization':{
				full_name:{caption:'Полное название'},
				short_name:{caption:'Краткое название'}
			}
		},
		type:{caption:'Тип лица'}
	},*/
	Identity:{
		id:{caption:'id', pk:true},
		organization:{caption:'Юридические лица',
			target:'Organisations',
			condition:[{there:'id',here:'id'}]
		},
		person:{caption:'Физические лица',
			target:'Persons',
			condition:[{there:'id',here:'id'}]
		}
	},
	Banks:{
		id:{caption:'id', pk:true},
		organization:{caption:'Организация',
			target:'Organisations',
			condition:[{there:'id',here:'organization'}]
		}
	},
	Sales:{
		id:{caption:'id', pk:true},
		buyer:{caption:'Покупатель',
			target:'Identity',
			condition:[{there:'id',here:'buyer'}]
		},
		saler:{caption:'Продавец',
			target:'Identity',
			condition:[{there:'id',here:'saler'}]
		},
		order_items:{caption:'Позиции в заказе',
			target:'Ordered_goods',
			condition:[{there:'order', here:'id'}],
			array:'auto'
		}
	},
	Ordered_goods:{
		id:{caption:'id', pk:true},
		date:{caption:'Дата'},
		summ:{caption:'Сумма'},
		order:{caption:'Заказ',
			target:'Sales',
			condition:[{there:'id',here:'order'}]
		}
	}
});