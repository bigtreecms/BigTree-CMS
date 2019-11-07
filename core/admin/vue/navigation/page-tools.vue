<script>
	Vue.component("PageTools", {
		props: ["tools"],
		data: function() {
			return {
				mutable_tools: this.tools
			}
		},
		mounted: function() {
			BigTreeEventBus.$on("add-page-tool", (tool) => {
				this.mutable_tools.push(tool);
			});

			BigTreeEventBus.$on("remove-page-tool", (tool) => {
				let mutated_tools = [];

				for (let i = 0; i < this.mutable_tools.length; i++) {
					let t = this.mutable_tools[i];

					if (t.title !== tool.title) {
						mutated_tools.push(this.mutable_tools[i]);
					}
				}

				this.mutable_tools = mutated_tools;
			});
		}
	});
</script>

<template>
	<div class="page_tools" v-if="mutable_tools.length">
		<div class="page_tools_body">
			<span v-for="tool in mutable_tools">
				<button v-if="tool.method" v-on:click="tool.method" class="page_tool" :title="translate(tool.title)">
					<icon wrapper="page_tool" :icon="tool.icon"></icon>
					<span class="page_tool_label">{{ translate(tool.title) }}</span>
				</button>
				<a v-else class="page_tool" :href="tool.url" :title="translate(tool.title)">
					<icon wrapper="page_tool" :icon="tool.icon"></icon>
					<span class="page_tool_label">{{ translate(tool.title) }}</span>
				</a>
			</span>
		</div>
	</div>
</template>