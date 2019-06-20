Vue.component("page-title", {
	props: ["title", "href"],
	template:
		'<h2 class="page_title">' +
			'<a class="page_link" v-bind:href="url">' +
				'<span class="page_label">{{ title }}</span>' +
				'<icon type="link"></icon>' +
			'</a>' +
		'</h2>'
});