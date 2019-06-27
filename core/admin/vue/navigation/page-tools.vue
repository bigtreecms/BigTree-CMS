<script>
	Vue.component("page-tools", {
		props: ["links"]
	});
</script>

<template>
	<div class="page_tools">
		<div class="page_tools_body">
			<a v-for="link in links" class="page_tool" :href="link.url">
				<icon wrapper="page_tool" :icon="link.icon"></icon>
				<span class="page_tool_label">{{ link.title }}</span>
			</a>
		</div>
	</div>
</template>