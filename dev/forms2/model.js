/*
	types
		I = VARCHAR(12) = ID

		Str = VARCHAR(254)
		Long = VARCHAR(1000)
		Text = TEXT

		N = NUMBER 
			(s,p)

		B = NUMBER(1) 0,1
			+ default = 0, if not null
		V = NUMBER(1) one value = 1

		D = DATE
			time
			date+time
			length in days
			length in hours (?)
		

		M = BLOB
			+ mime types

		R = rel
			= pk of target
		R2..R9 = adv rel fields

		X = list of vals
*/

var all = {}

var Model = {
	person_cache: {
		name: 'Люди',
		S: {
			rid: {
				name: 'Идентификатор',
				pk: true,
				type: 'S', //default
				subtype: 'person', //mailto:,vk:,facebook:
			},
			cached_name: {
				name: 'Имя',
				type: 'L'
			}
			cached_pict: {
				name: 'Картинка',
				type: 'M'
			}
		}
	},

	person_links: {
		name: 'Псевдонимы',
		S: {
			person: {
				name: 'Человек',
				pk: true,
			},
			list_id: { 
				name: 'Номер списка',
				pk: true,
				type: 'I',
			}
			alias: {
				name: 'Другое имя (псевдоним)',
				pk: true,
				subtype: 'person', //mailto:,vk:,facebook:
			},
			local_name: { name: 'Локальное переименование' }
		}
	},

	site_cache: {
		name: 'Организации',
		S: {
			rid: {
				name: 'Идентификатор',
				pk: true,
				subtype: 'URI',
			},
			cached_name: {
				name: 'Имя',
				type: 'L'
			}
			cached_pict: {
				name: 'Картинка',
				type: 'M'
			}
			person: {
				name: 'Куратор',
				subtype: 'person'
			},
		}
	},

	person_orgs: {
		name: 'Свои организиции',
		S: {
			person: {
				name: 'Человек',
				pk: true,
			},
			org: {
				name: 'Организация',
				pk: true,
			}
		}
	},

	actions: {
		name: 'Дела',
		S: {
			rid: { 
				name: 'ID',
				pk: true,
				type: 'I'
			}
			person: {
				name: 'Рассказчик',
				subtype: 'person' //mailto:,vk:,facebook:
			},
			name: {
				name: 'Название'
			},
			pict: {
				name: 'Картинка',
				type: 'M'
			},

			short_desk: {
				name: 'Суть',
			},
			long_desk: {
				name: 'Описание',
				type: 'L'
			},
			date_begin: {
				name: 'Дата'
			},
			date_end: {
				name: 'Дата окончания'
				// if none - one day
			},
			time: { name: 'Время' },
			length: { name: 'Продолжительность'
			},

			template: { name: 'Деятельность',
				type: 'V'
			},
			
			closed: { name: 'Закрыта',
				type: 'V'
			}
			report: {
				name: 'Отчет',
				type: 'T'
			}
		}
	},

	action_persons: {
		name: 'Участники',
		S: {
			action: {
				name: 'Дело',
				type: R,
				target: Model.actions,
				pk: true
			},
			person: {
				name: 'Участник',
				subtype: 'person',
				pk: true
			},
			coord: {
				name: 'Координатор',
				type: 'V'
			}
		}
	},
	action_orgs: {
		name: 'Организации дел',
		S: {
			action: {
				name: 'Дело',
				type: R,
				target: Model.actions,
				pk: true
			},
			org: {
				name: 'Организация',
				subtype: 'URI',
				pk: true
			},
			coord: {
				name: 'Координатор',
				type: 'V'
			},
			sponsor: {
				name: 'Спонсор',
				type: 'V'
			},
			appruved: {
				name: 'Подтверждено куратором',
				type: 'B'
			}
		}
	}
}
