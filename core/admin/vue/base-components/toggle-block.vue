<script>
	Vue.component("toggle-block", {
		props: ["id", "escaped_title", "title", "collapsed"],
		data: function() {
			return {
				expanded: !this.collapsed
			}
		},
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
</script>

<template>
	<div class="component layout_expanded" :class="{ 'collapsed' : collapsed }">
		<h2 class="component_title">
			<button v-on:click="toggle" class="component_expander" type="button">
				<icon wrapper="component_expander" icon="expand_more"></icon>
				<icon wrapper="component_expander" icon="expand_less"></icon>
			</button>
			<span v-if="escaped_title" v-html="title" class="component_title_label"></span>
			<span v-else class="component_title_label">{{ title }}</span>
		</h2>
		
		<div class="component_body">
			<slot></slot>
		</div>
	</div>
</template>