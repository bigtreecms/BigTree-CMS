/* global state, Vue */

new Vue({
	el: "#js-vue",
	data: {
		breadcrumb: typeof state.breadcrumb != "undefined" ? state.breadcrumb : [],
		page_title: typeof state.page_title != "undefined" ? state.page_title : "",
		page_public_url: typeof state.page_public_url != "undefined" ? state.page_public_url : "",
		tools: typeof state.tools != "undefined" ? state.tools : [],
		sub_nav: typeof state.sub_nav != "undefined" ? state.sub_nav : [],
		meta_bar: typeof state.meta_bar != "undefined" ? state.meta_bar : [],
		main_nav: state.main_nav
	},
	computed: state.computed
});