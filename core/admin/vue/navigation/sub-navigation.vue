<script>
	Vue.component("NavigationSub", {
		props: ["links"]
	});
</script>

<template>
	<nav class="sub_nav">
		<ul class="sub_nav_items">
			<li v-for="link in links" class="sub_nav_item">
				<a class="sub_nav_link" :href="link.url" :data-tooltip-title="link.tooltip"
				   :class="{ 'active': link.active, 'js-tooltip': link.tooltip, 'has_tooltip': link.tooltip }">
					{{ translate(link.title) }}
				</a>
				<div v-if="link.tooltip" class="sub_nav_hint">
					<span class="sub_nav_hint_label">{{ link.tooltip.title }}</span>
					<span class="sub_nav_hint_content">{{ link.tooltip.content }}</span>
				</div>
			</li>
		</ul>
	</nav>
</template>