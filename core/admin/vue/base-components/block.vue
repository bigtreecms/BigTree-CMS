<script>
	Vue.component("block", {
		props: ["title"],
	});
</script>

<template>
	<div class="component layout_expanded">
		<h2 class="component_title">
			<span class="component_title_label">{{ title }}</span>
		</h2>
		
		<div class="component_body">
			<slot></slot>
		</div>
	</div>
</template>