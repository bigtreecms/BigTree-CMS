Vue.component("toggle-section", {
	props: ["title", "collapsed"],
	data: function() {
		return {
			expanded: !this.collapsed
		}
	},
	template:
		`<div class="component layout_expanded" :class="{ 'collapsed' : collapsed }">
			<h2 class="component_title">
				<button v-on:click="toggle" class="component_expander" type="button">
					<icon wrapper="component_expander" icon="expand_more"></icon>
					<icon wrapper="component_expander" icon="expand_less"></icon>
				</button>
				<span class="component_title_label">{{ title }}</span>
			</h2>
			
			<div class="component_body">
				<slot></slot>
			</div>
		</div>`,
	methods: {
		toggle: function() {
			if (this.expanded) {
				this.expanded = false;
				$(this.$el).addClass("collapsed");
			} else {
				this.expanded = true;
				$(this.$el).removeClass("collapsed");
			}
		}
	}
});