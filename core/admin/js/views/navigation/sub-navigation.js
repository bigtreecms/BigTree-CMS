Vue.component("sub-navigation", {
	props: ["links"],
	template:
		`<nav class="sub_nav">
			<ul class="sub_nav_items">
				<li v-for="link in links" class="sub_nav_item">
					<a class="sub_nav_link" :class="{ 'active': link.active, 'has_tooltip': link.tooltip }" :href="link.url">
						{{ link.title }}
					</a>
					<div v-if="link.tooltip" class="sub_nav_hint">
						<span class="sub_nav_hint_label">{{ link.tooltip.title }}</span>
						<span class="sub_nav_hint_content">{{ link.tooltip.content }}</span>
					</div>
				</li>
			</ul>
		</nav>`
});