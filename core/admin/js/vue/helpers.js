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

		hash: function(str, seed = 0) {
			// Courtesy of bryc on Stack Overflow: https://stackoverflow.com/a/52171480/2762732
			let h1 = 0xdeadbeef ^ seed, h2 = 0x41c6ce57 ^ seed;
			for (let i = 0, ch; i < str.length; i++) {
				ch = str.charCodeAt(i);
				h1 = Math.imul(h1 ^ ch, 2654435761);
				h2 = Math.imul(h2 ^ ch, 1597334677);
			}
			h1 = Math.imul(h1 ^ h1>>>16, 2246822507) ^ Math.imul(h2 ^ h2>>>13, 3266489909);
			h2 = Math.imul(h2 ^ h2>>>16, 2246822507) ^ Math.imul(h1 ^ h1>>>13, 3266489909);

			return 4294967296 * (2097151 & h2) + (h1>>>0);
		},

		navigate: function(ev) {
			ev.preventDefault();
			let link = $(ev.target).closest("a");

			BigTreeAPI.resetWatchedStores();
			BigTree.request_partial(link.attr("href"));
		},

		object_length: function(obj) {
			let x = 0;

			for (let key in obj) {
				if (obj.hasOwnProperty(key)) {
					x++;
				}
			}

			return x;
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