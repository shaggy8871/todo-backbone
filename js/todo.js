(function ($) {

	/* 
	 * Todo list, modify and save
	 * Copyright (C) 2013, John Ginsberg
	 */

	/*
	 * NS
	 */
	window.todo = {
		Models: {},
		Collections: {},
		Views: {},
		Router: {}
	};

	/* 
	 * External templates
	 */
	window.template = function (id) {
		return _.template ($('#' + id).html ());
	};

	window.dateToString = function (date) {
		var dateExt = ((date == 1) || (date == 21) ? 'st' : ((date == 2) || (date == 22) ? 'nd' : ((date == 3) || (date == 23) ? 'rd' : 'th')));
		var dateHour = (date.getHours () < 10 ? '0' + date.getHours () : date.getHours ());
		var dateMin = (date.getMinutes () < 10 ? '0' + date.getMinutes () : date.getMinutes ());
		var monthNames = new Array ("Jan", "Feb", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
		return date.getDate () + dateExt + ' ' + monthNames[date.getMonth () - 1] + ' ' + date.getFullYear () + ' ' + dateHour + ':' + dateMin;
	}

	todo.Router = Backbone.Router.extend ({
		routes: {
			'': 'index'
		},
		index: function () {
			if (!window.list) {
				window.list = new todo.Views.List ();
			}
		}
	});

	/* 
	 * Entry model, stores the basic structure of a todo list entry
	 */
	todo.Models.Entry = Backbone.Model.extend ({
		idAttribute: "_id",
		defaults: {
			title: '',
			created: '',
			dueBy: '',
			priority: 0,
			details: '',
			completed: false
		}
	});

	/* 
	 * List (collection of entries)
	 */
	todo.Collections.List = Backbone.Collection.extend ({
		defaults: {
			model: todo.Models.Entry
		},
		model: todo.Models.Entry,
		url: '/todo/core/ToDo.php/',
		comparator: 'priority',
		// The function below is an override to ensure it triggers handlers on reset
		reset: function (models, options) {
			models  || (models = []);
			options || (options = {});
			for (var i = 0, l = this.models.length; i < l; i++) {
				this._removeReference (this.models[i]);
				this.models[i].trigger ('remove', this.models[i], this);
			}
			this._reset();
			this.add(models, _.extend ({silent: true}, options));
			if (!options.silent) {
				this.trigger ('reset', this, options);
			}
			return this;
		}
	});

	/* 
	 * Entry model view
	 */
	todo.Views.Entry = Backbone.View.extend ({
		tagName: 'li',
		events: {
			'click input[type="checkbox"]': 'completed',
			'click .edit': 'edit',
			'click .delete': 'del',
			'click .cancel': 'cancelEdit',
			'click .save': 'save'
		},
		entryTemplate: template ('entryTemplate'),
		initialize: function () {
			_.bindAll (this, 'render', 'unrender');
			this.model.bind ('change', this.render, this);
			this.model.bind ('remove', this.unrender, this);
		},
		render: function (e) {
			$(this.el).html (this.entryTemplate (this.model.toJSON ()));
			return this;
		},
		unrender: function () {
			$(this.el).remove ();
		},
		completed: function () {
			console.log ('Ticked');
			this.model.set ({ completed: !(this.model.get ('completed')) });
			this.model.save ();
		},
		edit: function () {
			console.log ('Editing...');
			this.$el.find ('span').css ('display', 'none');
			this.$el.find ('input[type="text"], button, textarea').css ('display', 'inline');
			this.$el.find ('.editDueBy').datetimepicker ({
				format: 'jS M Y H:i',
				minDate: 0
			});
		},
		cancelEdit: function () {
			console.log ('Cancel Editing...');
			this.$el.find ('span').css ('display', 'inline');
			this.$el.find ('input[type="text"], button, textarea').css ('display', 'none');
			return false;
		},
		save: function () {
			if (this.$el.find ('.editTitle').val () == '') {
				alert ('Please enter a title for your task');
				this.$el.find ('.editTitle').focus ();
				return false;
			}
			if (this.$el.find ('.editDueBy').val () == '') {
				alert ('Please enter a due date');
				this.$el.find ('.editDueBy').focus ();
				return false;
			}
			if ((isNaN (this.$el.find ('.editPriority').val ())) || (this.$el.find ('.editPriority').val () <= 0)) {
				alert ('Priority must be a number greater than 1');
				this.$el.find ('.editPriority').focus ();
				return false;
			}
			this.model.set ({ title: this.$el.find ('.editTitle').val (), dueBy: this.$el.find ('.editDueBy').val (), priority: this.$el.find ('.editPriority').val (), details: this.$el.find ('.editDetails').val () });
			this.model.save ();
			this.$el.find ('span').css ('display', 'inline');
			this.$el.find ('input[type="text"], button, textarea').css ('display', 'none');
			$('#list>li').tsort ('.priority span');
			console.log ('Saved!');
			return false;
		},
		del: function () {
			if (confirm ('Are you sure?')) {
				this.model.destroy ();
			}
		}
	});

	/* 
	 * List collection view
	 */
	todo.Views.List = Backbone.View.extend ({
		el: $('body'),
		events: {
			'click button': 'addNew'
		},
		initialize: function () {
			_.bindAll (this, 'render', 'addOne');
			this.collection = new todo.Collections.List ();
			this.collection.bind ('add', this.addOne, this);
			this.render ();
			this.collection.fetch ({
				add: true,
				success: function (collection, response, options) {
					$('#newtask').show ();
					$('#logout').show ();
					_(collection.models).each (function (entry) {
						entry.set ('_id', entry.get ('id'));
						entry.set ('dueBy', window.dateToString (new Date (entry.get ('dueBy'))));
					}, this);
				},
				error: function (collection, response, options) {
					if (response.status == 401) {
						$('#login').show ();
					}
					console.log ('Error!');
				}
			});
		},
		render: function () {
			$(this.el).append ("<div id='newtask'><input type='text' id='title' placeholder='Enter your task' /><br /><input type='text' id='dueBy' placeholder='Due by' /><br /><textarea id='details' placeholder='Enter details about your task'></textarea><br /><button>Add todo</button></div><ul id='list'></ul>");
			$('#dueBy').datetimepicker ({
				minDate: 0,
				format: 'jS M Y H:i'
			});
			return this;
		},
		addOne: function (entry) {
			var entryView = new todo.Views.Entry ({
				model: entry
			});
			$('#list').append (entryView.render ().el);
			$('#list>li').tsort ('.priority span');
		},
		addNew: function () {
			if ($('#title').val () == '') {
				alert ('Please enter a title for your task');
				$('#title').focus ();
				return false;
			}
			if ($('#dueBy').val () == '') {
				alert ('Please enter a due date');
				$('#dueBy').focus ();
				return false;
			}
			var entry = this.collection.create ({ title: $('#title').val (), dueBy: $('#dueBy').val (), priority: 0, details: $('#details').val (), completed: false });
			// Clear values
			$('#title').val ('');
			$('#dueBy').val ('');
			$('#details').val ('');
		}
	});

	app = new todo.Router;
	Backbone.history.start();

}) (jQuery);