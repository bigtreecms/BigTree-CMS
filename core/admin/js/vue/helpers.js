Vue.mixin({
	data: function() {
		return {
			WWW_ROOT: WWW_ROOT,
			ADMIN_ROOT: ADMIN_ROOT
		}
	},
	methods: {
		color_mesh: function(from, to, percentage) {
			const hexdec = (hex) => {
				return parseInt(hex, 16);
			};

			if (from.substring(0, 1) === "#") {
				from = from.substring(1);
			}

			if (to.substring(0, 1) === "#") {
				to = to.substring(1);
			}

			const from_red = hexdec(from.substring(0, 2));
			const from_green = hexdec(from.substring(2, 4));
			const from_blue = hexdec(from.substring(4, 6));

			const to_red = hexdec(to.substring(0, 2));
			const to_green = hexdec(to.substring(2, 4));
			const to_blue = hexdec(to.substring(4, 6));

			const red_diff = Math.ceil((to_red - from_red) * percentage);
			const green_diff = Math.ceil((to_green - from_green) * percentage);
			const blue_diff = Math.ceil((to_blue - from_blue) * percentage);

			let new_red = (from_red + red_diff).toString(16);
			let new_green = (from_green + green_diff).toString(16);
			let new_blue = (from_blue + blue_diff).toString(16);

			if (new_red.length === 1) {
				new_red = "0" + new_red;
			}

			if (new_green.length === 1) {
				new_green = "0" + new_green;
			}

			if (new_blue.length === 1) {
				new_blue = "0" + new_blue;
			}

			return "#" + new_red + new_green + new_blue;
		},

		navigate: function(ev) {
			ev.preventDefault();
			let target = $(ev.target);

			BigTree.request_partial(target.attr("href"));
		},

		translate: function(string, tokens) {
			let translated_string = string;

			if (typeof VueLanguagePack[string] !== "undefined") {
				translated_string = VueLanguagePack[string];
			}

			if (typeof tokens == "object") {
				for (let key in tokens) {
					if (tokens.hasOwnProperty(key)) {
						translated_string = translated_string.replace(key, tokens[key]);
					}
				}
			}

			return translated_string;
		},

		hook_tooltips: function(el) {
			$(el).find(".js-tooltip").each(function() {
				if ($(this).hasClass("has_tooltip")) {
					return;
				}

				const content = $(this).attr("data-tooltip-content");
				const title = $(this).attr("data-tooltip-title");
				const tooltip = $('<div class="tooltip">');

				tooltip.append($('<div class="tooltip_title">').html(title));
				tooltip.append($('<div class="tooltip_content">').html(content));

				$(this).addClass("has_tooltip").append(tooltip);

				if (tooltip.offset().top - tooltip.outerHeight() < 10) {
					tooltip.addClass("flipped");
				}
			});
		}
	},
	mounted: function() {
		this.hook_tooltips(this.$el);
	},
	updated: function() {
		this.hook_tooltips(this.$el);
	}
});

const FieldType = Vue.extend({
	props: [
		"title",
		"subtitle",
		"name",
		"value",
		"required"
	],

	data: function() {
		return {
			current_value: this.value,
			error: "",
			uid: this._uid
		}
	},

	methods: {
		validate: function() {
			if (this.required && !this.current_value) {
				this.error = this.translate("Required");

				return;
			}

			this.error = null;
			this.$parent.$emit("validated");
		}
	},

	mounted: function() {
		if (this.required && this.$parent && this.$parent.increment_validation_total) {
			this.$parent.increment_validation_total();
			BigTreeEventBus.$on("form-block-validation", this.validate);
		}
	}
});