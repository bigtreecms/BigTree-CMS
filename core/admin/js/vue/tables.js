const BigTreeTable = Vue.extend({
	props: [
		"action_calculator",
		"actions",
		"actions_base_path",
		"clickable_rows",
		"columns",
		"data",
		"data_contains_actions",
		"default_sort_column",
		"default_sort_direction",
		"escaped_data",
		"searchable",
		"search_label",
		"search_placeholder",
		"view_cache_sort"
	],

	data: function() {
		return {
			id: null,
			mutable_data: null,
			query: "",
			query_field_value: "",
			query_timer: null,
			sort_column: this.default_sort_column,
			sort_direction: this.default_sort_direction
		}
	},

	watch: {
		data: function(new_val, old_val) {
			this.mutable_data = new_val;
		},
		query_field_value: function() {
			$(this.$el).find(".search").addClass("loading");
		},
		filtered_data: function() {
			$(this.$el).find(".search").removeClass("loading");
		}
	},

	computed: {
		filtered_data: function() {
			let data = this.mutable_data ? this.mutable_data : this.data;

			if (!data || !data.length) {
				return [];
			}

			if (this.query !== "") {
				let query = this.query.toLowerCase();
				data = [];

				for (let x = 0; x < this.mutable_data.length; x++) {
					let entry = this.mutable_data[x];

					for (let index = 0; index < this.columns.length; index++) {
						let column = entry[this.columns[index].key].toLowerCase();

						if (column.indexOf(query) > -1) {
							data.push(entry);
						}
					}
				}
			}

			if (this.sort_column) {
				data.sort((a, b) => {
					const a_val = a[this.sort_column].toLowerCase();
					const b_val = b[this.sort_column].toLowerCase();

					if (a_val === b_val) {
						return 0;
					}

					return (a_val < b_val) ? -1 : 1;
				});
			}

			if (this.sort_direction === "DESC") {
				data.reverse();
			}

			return data;
		}
	},

	methods: {
		equalize_actions: function() {
			// Make all the action menus be equal width
			const $items = $(this.$el).find(".action_menu_item");
			const $labels = $(this.$el).find(".action_menu_label");
			const padding_left = parseInt($labels.css("padding-left"));
			const padding_right = parseInt($labels.css("padding-right"));

			let widest = 0;
			let unique_action_titles = [];

			$items.each(function() {
				let text = $(this).text();

				if (unique_action_titles.indexOf(text) === -1) {
					unique_action_titles.push(text);
				}
			});

			$.each(unique_action_titles, function(key, value) {
				const $tester = $('<div class="action_menu_label" style="position: absolute; left: -1000px;">').text(value);
				$("body").append($tester);

				const label_width = parseInt($tester.width());
				$tester.remove();

				if (label_width > widest) {
					widest = label_width;
				}
			});

			$labels.css({ minWidth: (widest + padding_left + padding_right) + "px" });
		},

		query_key_up: function() {
			if (this.query_timer) {
				clearTimeout(this.query_timer);
			}

			let timeout = Math.ceil(this.mutable_data.length / 3);

			if (timeout > 500) {
				timeout = 500;
			} else if (timeout < 50) {
				timeout = 50;
			}

			this.query_timer = setTimeout(this.query_parse, timeout);
		},

		query_parse: function() {
			this.query = this.query_field_value;
		},

		row_click: function(event, data) {
			event.preventDefault();

			const index = $(event.target).data("index");
			this.$emit("row-click", this.paged_data[index]);
		}
	},

	mounted: function() {
		this.id = this._uid;
		this.equalize_actions();
		this.mutable_data = this.data;

		// Figure out which column is the default sort
		for (let i = 0; i < this.columns.length; i++) {
			let column = this.columns[i];

			if (column.sort_default) {
				if (typeof column.sort_default_direction !== "undefined") {
					this.sort_direction = column.sort_default_direction;
				} else {
					this.sort_direction = "ASC";
				}

				this.sort_column = column.key;
			}
		}

		// View cache tables might be using a hidden field for sorting
		if (!this.sort_column && this.view_cache_sort) {
			this.sort_column = "sort_field";
			this.sort_direction = this.view_cache_sort;
		}
	},

	updated: function() {
		this.equalize_actions();
	}
});