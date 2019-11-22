<script>
	Vue.component("ModuleViewGrouped", {
		extends: BigTreeModuleView,
		props: ["draggable", "groups"],
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
		<data-table :id="id" :draggable="draggable" escaped_data="true"
					:groups="groups" group_by="group_field"
					:data="data" :data_contains_actions="true" :columns="columns"
					:action_calculator="action_calculator"
					:actions_base_path="actions_base_path"></data-table>
	</div>
</template>