Vue.component("main-navigation", {
	props: ["links", "title"],
	template:
		`<div>
			<button class="js-menu-toggle menu_toggle">
				<icon wrapper="menu_toggle" icon="menu"></icon>
				<span class="menu_toggle_label">{{ title }}</span>
			</button>
			<div class="js-menu menu">
				<nav class="main_nav">
					<ul class="main_nav_items">
						<li v-for="link in links" class="main_nav_item">
							<a class="main_nav_link" :class="{ 'active': link.active }" :href="link.url">
								<icon wrapper="main_nav" :icon="link.icon"></icon>
								<span class="main_nav_label">{{ link.title }}</span>
							</a>
							<div v-if="link.active && link.children.length" class="main_nav_children">
								<a v-for="child in link.children" class="main_nav_child" :href="child.url">{{ child.title }}</a>
							</div>
						</li>
					</ul>
				</nav>
			</div>
		</div>`
});