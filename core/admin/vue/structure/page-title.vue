<script>
	Vue.component("page-title", {
		props: ["title", "url"]
	});
</script>

<template>
	<h2 class="page_title">
		<template v-if="url">
			<a class="page_link" :href="url" target="_blank">
				<span class="page_label">{{ title }}</span>
				<icon wrapper="page" icon="link"></icon>
			</a>
		</template>
		<template v-else>
			<span class="page_label">{{ title }}</span>
		</template>
	</h2>
</template>