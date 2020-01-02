<script>
	Vue.component("DeveloperCalloutList", {
		asyncComputed: {
			async tables() {
				let data = await BigTreeAPI.getStoredData("callouts", "name");

				return [
					{
						id: "callouts",
						actions_base_path: "developer/callouts",
						actions: [
							{
								title: this.translate("Edit"),
								route: "edit"
							},
							{
								title: this.translate("Delete"),
								route: "delete",
								method: this.delete,
								confirm: this.translate("Are you sure you want to delete this callout?")
							}
						],
						data: data,
						columns: [
							{
								title: this.translate("Callout Name"),
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
					endpoint: "callouts/delete",
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
						context: this.translate("Callouts"),
						message: this.translate("Deleted Callout")
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
	<grouped-tables searchable="true" escaped_data="true" search_placeholder="Search Callouts"
				   search_label="Search Callouts" :tables="tables"></grouped-tables>
</template>