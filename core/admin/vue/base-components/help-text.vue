<script>
	Vue.component("HelpText", {
		props: ["text"],
		data: function() {
			let hidden = false;
			const hash = this.hash(this.text);
			const cookie = $.cookie("bigtree-admin-hidden-help-text");

			if (cookie) {
				let hidden_entries = JSON.parse(cookie);

				for (let i = 0; i < hidden_entries.length; i++) {
					if (hidden_entries[i] === hash) {
						hidden = true;
					}
				}
			}

			return {
				hashed_text: hash,
				hidden: hidden
			};
		},
		methods: {
			add_tool: function() {
				BigTreeEventBus.$emit("add-page-tool", {
					title: "View Help Text",
					method: this.show,
					icon: "help"
				});
			},
			hide: function(ev) {
				ev.preventDefault();

				let cookie = $.cookie("bigtree-admin-hidden-help-text");
				let hidden_entries;

				if (cookie) {
					hidden_entries = JSON.parse(cookie);
					hidden_entries.push(this.hashed_text);
				} else {
					hidden_entries = [this.hashed_text];
				}

				$.cookie("bigtree-admin-hidden-help-text", JSON.stringify(hidden_entries));
				this.hidden = true;
				this.add_tool();
			},
			remove_tool: function() {
				BigTreeEventBus.$emit("remove-page-tool", {
					title: "View Help Text",
					method: this.show,
					icon: "help"
				});
			},
			show: function(ev) {
				ev.preventDefault();

				let cookie = $.cookie("bigtree-admin-hidden-help-text");
				let current_hidden = JSON.parse(cookie);
				let hidden_entries = [];

				for (let i = 0; i < current_hidden.length; i++) {
					if (current_hidden[i] !== this.hashed_text) {
						hidden_entries.push(current_hidden[i]);
					}
				}

				this.hidden = false;
				this.remove_tool();

				$.cookie("bigtree-admin-hidden-help-text", JSON.stringify(hidden_entries));
			}
		},
		mounted: function() {
			if (this.hidden) {
				this.add_tool();
			}
		},
		updated: function() {
			if (!this.hidden) {
				$(this.$el).find(".js-focusable").focus();
			}
		}
	});
</script>

<template>
	<div class="component_body" v-if="!hidden">
		<div class="alert js-focusable" tabindex="0">
			<div class="alert_description">{{ text }}</div>

			<button class="alert_close" v-on:click="hide">
				<icon wrapper="alert_close" icon="clear"></icon>
				<span class="alert_close_text">{{ translate('Hide Help Text') }}</span>
			</button>
		</div>
	</div>
</template>