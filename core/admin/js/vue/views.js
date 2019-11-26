const BigTreeModuleView = Vue.extend({
	props: [
		"actions",
		"actions_base_path",
		"fields",
		"help_text",
		"id",
		"module",
		"title"
	],
	data: function() {
		let columns = [];
		let x = 1;

		for (let index in this.fields) {
			if (this.fields.hasOwnProperty(index)) {
				let field = this.fields[index];
				let sort_default = false;
				let sort_default_direction = "ASC";

				if (typeof this.sort_column !== "undefined" && this.sort_column === index) {
					sort_default = true;

					if (typeof this.sort_direction !== "undefined") {
						sort_default_direction = this.sort_direction;
					}
				}

				columns.push({
					key: "column" + x++,
					title: field.title,
					width: field.width,
					type: field.type ? field.type : "text",
					prefix: field.prefix ? field.prefix : "",
					sort: true,
					sort_default: sort_default,
					sort_default_direction: sort_default_direction
				});
			}
		}

		columns.push({
			key: "status",
			title: this.translate("Status"),
			type: "status"
		});

		return {
			columns: columns
		};
	},

	asyncComputed: {
		async data() {
			let data = await BigTreeAPI.getStoredDataMatching("view-cache", "view", this.id);

			if (this.draggable) {
				data.sort((a, b) => {
					const a_val = parseInt(a.position);
					const b_val = parseInt(b.position);

					if (a_val === b_val) {
						return 0;
					}

					return (a_val > b_val) ? -1 : 1;
				});
			}

			return data;
		}
	},

	methods: {
		action: async function(id, index) {
			let real_index = null;
			let x = 0;

			for (let action_index in this.actions) {
				if (this.actions.hasOwnProperty(action_index)) {
					if (x === index) {
						real_index = action_index;
					}

					x++;
				}
			}

			let response = await BigTreeAPI.call({
				endpoint: "modules/views/get-action-url",
				method: "GET",
				parameters: {
					module: this.module,
					view: this.id,
					entry: id,
					action: real_index
				}
			});

			document.location.href = response.url;
		},

		action_calculator: function(data) {
			let actions = [];

			if (data.access_level === "n" || !data.access_level) {
				return [];
			}

			for (let index in this.actions) {
				if (this.actions.hasOwnProperty(index)) {
					let action = this.actions[index];

					if (action === "on") {
						if (index === "edit") {
							actions.push({
								title: "Edit",
								route: "edit"
							});
						} else if (index === "delete" && data.access_level === "p") {
							actions.push({
								title: "Delete",
								method: this.delete,
								confirm: "Are you sure you wish to delete this entry?"
							});
						}
					} else {
						action = JSON.parse(action);

						if (typeof action === "object") {
							if (action.route) {
								actions.push({
									title: action.name,
									route: action.route
								});
							} else if (action.function) {
								actions.push({
									title: action.name,
									method: this.action.bind(action)
								});
							}
						}
					}
				}
			}

			return actions;
		},

		delete: async function(id) {
			await BigTreeAPI.call({

			});
		}
	}
});