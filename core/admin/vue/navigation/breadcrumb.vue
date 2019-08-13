<script>
	Vue.component("breadcrumb", {
		props: ["links"],
		methods: {
			click: function(event) {
				BigTreeEventBus.$emit("breadcrumb-click", $(event.target).data("id"));
			}
		}
	});
</script>

<template>
	<nav class="breadcrumb">
		<span v-for="link in links" class="breadcrumb_item">
			<a v-if="typeof link.id !== 'undefined'" v-on:click="click" :data-id="link.id" :href="link.url" class="breadcrumb_link">{{ translate(link.title) }}</a>
			<a v-else class="breadcrumb_link" :href="link.url">{{ translate(link.title) }}</a>
		</span>
	</nav>
</template>