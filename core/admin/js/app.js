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
		},

		request_partial: function(url) {
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
		}
	},
	mounted: function() {
		$(window).on("popstate", function(event) {
			let pop = event.originalEvent.state;

			if (!pop || !pop.state) {
				window.location.reload();

				return;
			}

			for (let key in pop.state) {
				if (pop.state.hasOwnProperty(key)) {
					BigTree[key] = pop.state[key];
				}
			}

			let res = Vue.compile('<div id="content">' + pop.content + '</div>');
			new Vue({
				render: res.render,
				staticRenderFns: res.staticRenderFns
			}).$mount('#content')
		});
	}
});
