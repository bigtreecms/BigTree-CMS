<script>
	Vue.component("ButtonBar", {
		props: ["links"]
	});
</script>

<template>
	<div class="component">
		<div class="component_body">
			<div class="links">
				<a v-for="link in links" class="link_button" :href="link.url">
					<span class="link_button_inner">
						<icon v-if="link.icon" :icon="link.icon" wrapper="link_button"></icon>
						<span class="link_button_label">{{ translate(link.title) }}</span>
					</span>
				</a>
			</div>
		</div>
	</div>
</template>