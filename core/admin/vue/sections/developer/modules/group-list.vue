<script>
	Vue.component("ModuleGroupList", {
		asyncComputed: {
			async tables() {
				let groups = await BigTreeAPI.getStoredData("module-groups", "position", true);

				return [
					{
						id: "module-groups",
						actions_base_path: "developer/modules/groups",
						actions: [
							{
								title: this.translate("Edit"),
								route: "edit"
							},
							{
								title: this.translate("Delete"),
								method: this.delete,
								confirm: this.translate("Are you sure you want to delete this module module group?\nModules inside this group will become ungrouped.")
							}
						],
						data: groups,
						columns: [
							{
								title: "Group Name",
								key: "name",
								type: "text"
							}
						],
						draggable: true
					}
				];
			}
		},
		
		methods: {
			delete: async function(id) {
				await BigTreeAPI.call({
					endpoint: "module-groups/delete",
					method: "POST",
					parameters: {
						id: id
					}
				});

				this.$asyncComputed.tables.update();
			}
		},
		
		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "module-groups") {
					this.$asyncComputed.tables.update();
				}
			});
			
			BigTreeEventBus.$on("data-table-resorted", async (table) => {
				let data = table.mutable_data;

				let positions = {};
				let position = data.length;

				for (let x = 0; x < data.length; x++) {
					positions[data[x].id] = position;
					position--;
				}

				await BigTreeAPI.call({
					endpoint: "module-groups/order",
					method: "POST",
					parameters: {
						"positions": positions
					}
				});
			});
		}
	});
</script>

<template>
	<GroupedTables searchable="true" escaped_data="true" search_placeholder="Search Module Groups"
				   search_label="Search Module Groups" :tables="tables"></GroupedTables>
</template>