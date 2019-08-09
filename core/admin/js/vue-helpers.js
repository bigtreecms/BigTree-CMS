// Translate, Tooltip
Vue.mixin({
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
		}
	},
	mounted: function() {
		// Tooltips
		$(this.$el).find(".js-tooltip").each(function() {
			console.log(this);
			const content = $(this).data("tooltip-content");
			const title = $(this).data("tooltip-title");
			const tooltip = $('<div class="tooltip">');

			tooltip.append($('<div class="tooltip_title">').html(title));
			tooltip.append($('<div class="tooltip_content">').html(content));

			$(this).addClass("has_tooltip").append(tooltip);

			if (tooltip.offset().top - tooltip.outerHeight() < 10) {
				tooltip.addClass("flipped");
			}
		});
	}
});