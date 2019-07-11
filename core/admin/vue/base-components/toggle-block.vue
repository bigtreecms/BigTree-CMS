<script>
	Vue.component("toggle-block", {
		props: ["id", "escaped_title", "title", "collapsed"],
		data: function() {
			return {
				expanded: !this.collapsed
			};
		},
		methods: {
			toggle: function() {
				this.expanded = !this.expanded;

				// Doesn't support saved toggle states
				if (!this.id) {
					return;
				}

				let saved = {};

				try {
					saved = JSON.parse($.cookie("bigtree-toggle-block-states"));
				} catch (e) {}

				saved[this.id] = this.expanded ? "open" : "collapsed";
				$.cookie("bigtree-toggle-block-states", JSON.stringify(saved));
			}
		},
		mounted: function() {
			if (this.id) {
				let saved = {};

				try {
					saved = JSON.parse($.cookie("bigtree-toggle-block-states"));
				} catch (e) {}

				if (saved[this.id] === "collapsed") {
					this.expanded = false;
				} else if (saved[this.id] === "open") {
					this.expanded = true;
				}
			}
		}
	});
</script>

<template>
	<div class="component layout_expanded" :class="{ 'collapsed' : !expanded }">
		<h2 class="component_title">
			<button v-on:click="toggle" class="component_expander" type="button">
				<icon wrapper="component_expander" icon="expand_more"></icon>
				<icon wrapper="component_expander" icon="expand_less"></icon>
			</button>
			<span v-if="escaped_title" v-html="translate(title)" class="component_title_label"></span>
			<span v-else class="component_title_label">{{ translate(title) }}</span>
		</h2>
		
		<div class="component_body">
			<slot></slot>
		</div>
	</div>
</template>