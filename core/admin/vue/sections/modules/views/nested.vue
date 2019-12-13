<script>
	Vue.component("ModuleViewNested", {
		extends: BigTreeModuleView,
		mounted: function() {
			BigTreeEventBus.$on("data-table-resorted", async (table) => {
				let data = table.mutable_data;
				let children = [];

				for (let x = 0; x < data.length; x++) {
					children.push(data[x].id);
				}

				await BigTreeAPI.call({
					endpoint: "modules/views/order-entries",
					method: "POST",
					parameters: {
						"module": this.module,
						"view": this.id,
						"entries": children
					}
				});
			});
		}
	});
</script>

<template>
	<div class="component">
		<help-text v-if="help_text" :text="help_text"></help-text>
		<table-nested :id="id" escaped_data="true" :data="data" :data_contains_actions="true"
					  :columns="columns" nesting_column="group_field"
					  :action_calculator="action_calculator" :actions_base_path="actions_base_path">
		</table-nested>
	</div>
</template>