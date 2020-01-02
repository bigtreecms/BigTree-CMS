<script>
	Vue.component("DeveloperModuleGroupList", {
		asyncComputed: {
			async tables() {
				let data = await BigTreeAPI.getStoredData("module-groups", "position", true);

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
						data: data,
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
				let response = await BigTreeAPI.call({
					endpoint: "module-groups/delete",
					method: "POST",
					parameters: {
						id: id
					}
				});

				if (response.error) {
					BigTree.notification = {
						type: "error",
						context: this.translate("Deletion Failed"),
						message: this.translate(response.message)
					};
				} else {
					BigTree.notification = {
						type: "success",
						context: this.translate("Module Groups"),
						message: this.translate("Deleted Group")
					};

					this.$asyncComputed.tables.update();
				}
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
				let groups = [];

				for (let x = 0; x < data.length; x++) {
					groups.push(data[x].id);
				}

				await BigTreeAPI.call({
					endpoint: "module-groups/order",
					method: "POST",
					parameters: {
						"groups": groups
					}
				});
			});
		}
	});
</script>

<template>
	<grouped-tables searchable="true" escaped_data="true" search_placeholder="Search Module Groups"
				   search_label="Search Module Groups" :tables="tables"></grouped-tables>
</template>