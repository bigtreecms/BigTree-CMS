Vue.component("icon", {
	props: ["wrapper", "icon"],
	template:
		'<span v-bind:class="wrapper + \'_icon\'">' +
			'<svg v-bind:class="\'icon icon_\' + icon">' +
				'<use v-bind:xlink:href="\'admin_root/images/icons.svg#\' +  icon"></use>' +
			'</svg>' +
		'</span>'
});