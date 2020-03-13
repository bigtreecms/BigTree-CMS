/* global state, Vue, VueLanguagePack */
let BigTreeEventBus = new Vue();

let BigTree = new Vue({
	el: "#js-vue",
	data: {
		breadcrumb: typeof state.breadcrumb != "undefined" ? state.breadcrumb : [],
		layout: "",
		meta_bar: typeof state.meta_bar != "undefined" ? state.meta_bar : [],
		main_nav: state.main_nav,
		notification: state.notification ? state.notification : { context: "", message: "", type: "" },
		page_public_url: typeof state.page_public_url != "undefined" ? state.page_public_url : "",
		page_title: typeof state.page_title != "undefined" ? state.page_title : "",
		sub_nav: typeof state.sub_nav != "undefined" ? state.sub_nav : [],
		sub_nav_actions: typeof state.sub_nav_actions != "undefined" ? state.sub_nav_actions : [],
		theme: typeof state.theme !== "undefined" ? state.theme : "default",
		tools: typeof state.tools != "undefined" ? state.tools : [],
		url_cache: {},
		user_level: state.user_level
	},
	methods: {
		confirm: async function(message) {
			let response = confirm(message);

			return response;
		},

		growl: function(message) {
			message = this.translate(message);

			alert(message);
		},

		load_partial: function(url, state, content) {
			// Reset state
			BigTree.breadcrumb = [];
			BigTree.meta_bar = [];
			BigTree.page_title = "";
			BigTree.page_public_url = "";
			BigTree.sub_nav = [];
			BigTree.sub_nav_actions = [];
			BigTree.theme = "default";
			BigTree.tools = [];

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

			$("#main_content").focus();
			$('html, body').animate({ scrollTop: 0 }, 500);

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
					if (!response || typeof response.responseJSON === "undefined" ||
						(this.layout !== "" && response.responseJSON.layout !== this.layout))
					{
						window.location.href = url;

						return;
					}
					
					this.layout = response.responseJSON.layout;

					if (typeof response.responseJSON.no_cache === "undefined") {
						this.url_cache[url] = {
							state: response.responseJSON.state,
							content: response.responseJSON.content
						};
					}

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
