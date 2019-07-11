<script>
	Vue.component("breadcrumb", {
		props: ["links"]
	});
</script>

<template>
	<nav class="breadcrumb">
		<span v-for="link in links" class="breadcrumb_item"><a class="breadcrumb_link" :href="link.url">{{ translate(link.title) }}</a></span>
	</nav>
</template>