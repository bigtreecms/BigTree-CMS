<script>
	Vue.component("DeveloperCalloutGroupList", {
		asyncComputed: {
			async tables() {
				let data = await BigTreeAPI.getStoredData("callout-groups", "name");

				return [
					{
						id: "callout-groups",
						actions_base_path: "developer/callouts/groups",
						actions: [
							{
								title: this.translate("Edit"),
								route: "edit"
							},
							{
								title: this.translate("Delete"),
								route: "delete",
								method: this.delete,
								confirm: this.translate("Are you sure you want to delete this callout group?")
							}
						],
						data: data,
						columns: [
							{
								title: this.translate("Group Name"),
								key: "name"
							}
						]
					}
				];
			}
		},
		methods: {
			delete: async function(id) {
				let response = await BigTreeAPI.call({
					endpoint: "callout-groups/delete",
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
						context: this.translate("Callout Groups"),
						message: this.translate("Deleted Group")
					};
					
					this.$asyncComputed.tables.update();
				}
			}
		},
		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "callouts") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<grouped-tables searchable="true" escaped_data="true" search_placeholder="Search Callout Groups"
				   search_label="Search Callout Groups" :tables="tables"></grouped-tables>
</template>