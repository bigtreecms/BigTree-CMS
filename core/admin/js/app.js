/* global state, Vue, VueLanguagePack */
let BigTreeEventBus = new Vue();

let BigTree = new Vue({
	el: "#js-vue",
	data: {
		breadcrumb: typeof state.breadcrumb != "undefined" ? state.breadcrumb : [],
		page_title: typeof state.page_title != "undefined" ? state.page_title : "",
		page_public_url: typeof state.page_public_url != "undefined" ? state.page_public_url : "",
		tools: typeof state.tools != "undefined" ? state.tools : [],
		sub_nav: typeof state.sub_nav != "undefined" ? state.sub_nav : [],
		sub_nav_actions: typeof state.sub_nav_actions != "undefined" ? state.sub_nav_actions : [],
		meta_bar: typeof state.meta_bar != "undefined" ? state.meta_bar : [],
		main_nav: state.main_nav,
		user_level: state.user_level
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
