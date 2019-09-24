Vue.mixin({
	data: function() {
		return {
			WWW_ROOT: WWW_ROOT,
			ADMIN_ROOT: ADMIN_ROOT
		}
	},
	methods: {
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