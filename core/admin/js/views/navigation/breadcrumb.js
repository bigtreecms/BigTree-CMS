Vue.component("breadcrumb", {
	props: ["links"],
	template:
		`<nav class="breadcrumb">
			<span v-for="link in links" class="breadcrumb_item"><a class="breadcrumb_link" :href="link.url">{{ link.title }}</a></span>
		</nav>`
});