/* global state, Vue, VueLanguagePack */
let BigTreeEventBus = new Vue();

let BigTree = new Vue({
	el: "#js-vue",
	data: {
		breadcrumb: typeof state.breadcrumb != "undefined" ? state.breadcrumb : [],
		meta_bar: typeof state.meta_bar != "undefined" ? state.meta_bar : [],
		main_nav: state.main_nav,
		page_title: typeof state.page_title != "undefined" ? state.page_title : "",
		page_public_url: typeof state.page_public_url != "undefined" ? state.page_public_url : "",
		sub_nav: typeof state.sub_nav != "undefined" ? state.sub_nav : [],
		sub_nav_actions: typeof state.sub_nav_actions != "undefined" ? state.sub_nav_actions : [],
		tools: typeof state.tools != "undefined" ? state.tools : [],
		url_cache: {},
		user_level: state.user_level
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

		load_partial: function(url, state, content) {
			for (let key in state) {
				if (state.hasOwnProperty(key)) {
					BigTree[key] = state[key];
				}
			}

			window.history.pushState({
				"state": state,
				"content": content
			}, "", url);

			let res = Vue.compile('<div id="content">' + content + '</div>');
			new Vue({
				render: res.render,
				staticRenderFns: res.staticRenderFns
			}).$mount('#content');

			this.toggle_busy();
		},

		request_partial: function(url) {
			this.toggle_busy("Loading");

			if (typeof this.url_cache[url] !== "undefined") {
				this.load_partial(url, this.url_cache[url].state, this.url_cache[url].content);

				return;
			}

			$.ajax(url, {
				headers: {
					"BigTree-Partial": true
				},
				complete: (response) => {
					if (!response || typeof response.responseJSON == "undefined") {
						window.location.href = url;

						return;
					}

					this.url_cache[url] = {
						state: response.responseJSON.state,
						content: response.responseJSON.content
					};
					this.load_partial(url, response.responseJSON.state, response.responseJSON.content);
				}
			});
		},

		toggle_busy: function(message) {
			if (message) {
				$("#js-busy-message").html(this.translate(message) + "&hellip;");
				$("#js-busy").addClass("busy_working").show();
			} else {
				$("#js-busy").removeClass("busy_working").hide();
			}
		}
	},

	beforeCreate: function() {
		window.history.replaceState({
			"state": state,
			"content": $("#content").html()
		}, "", window.location.href);
	},

	mounted: function() {
		$(window).on("popstate", function(event) {
			let pop = event.originalEvent.state;
			let component_state = {};

			if (!pop) {
				window.location.reload();

				return;
			}

			for (let key in pop.state) {
				if (pop.state.hasOwnProperty(key)) {
					if (BigTree.hasOwnProperty(key)) {
						BigTree[key] = pop.state[key];
					}
				}
			}

			for (let key in pop) {
				if (pop.hasOwnProperty(key) && key !== "state" && key !== "content") {
					component_state[key] = pop[key];
				}
			}

			let res = Vue.compile('<div id="content">' + pop.content + '</div>');
			new Vue({
				render: res.render,
				staticRenderFns: res.staticRenderFns,
				data: component_state
			}).$mount('#content');
		});
	}
});
