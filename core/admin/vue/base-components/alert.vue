<script>
	Vue.component("Alert", {
		props: ["title", "type", "hideable"],
		computed: {
			icon: function() {
				if (this.type === "hint" || this.type === "notice") {
					return "warning";
				}
			}
		}
	});
</script>

<template>
	<div class="component layout_expanded">
		<div class="component_body">
			<div class="alert">
				<div v-if="title" class="alert_header">
					<icon wrapper="alert" :icon="icon"></icon>
					<span class="alert_title">{{ translate(title) }}</span>
				</div>

				<div class="alert_description">
					<slot></slot>
				</div>

				<button v-if="hideable" class="alert_close">
					<icon wrapper="alert_close" icon="clear"></icon>
					<span class="alert_close_text">Hide</span>
				</button>
			</div>
		</div>
	</div>
</template>