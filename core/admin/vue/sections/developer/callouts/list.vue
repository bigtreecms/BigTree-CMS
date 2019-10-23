<script>
	Vue.component("CalloutList", {
		asyncComputed: {
			async tables() {
				let callouts = await BigTreeAPI.getStoredData("callouts", "name");

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
						data: callouts,
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
				await BigTreeAPI.call({
					endpoint: "callouts/delete",
					method: "POST",
					parameters: {
						id: id
					}
				});

				this.$asyncComputed.tables.update();
			}
		}
	});
</script>

<template>
	<GroupedTables searchable="true" escaped_data="true" search_placeholder="Search Callouts"
				   search_label="Search Callouts" :tables="tables"></GroupedTables>
</template>