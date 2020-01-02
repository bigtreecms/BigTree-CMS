<script>
	Vue.component("DeveloperSettingList", {
		asyncComputed: {
			async tables() {
				let data = await BigTreeAPI.getStoredData("settings", "name");

				return [
					{
						id: "settings",
						actions_base_path: "developer/settings",
						actions: [
							{
								title: this.translate("Edit"),
								route: "edit"
							},
							{
								title: this.translate("Delete"),
								route: "delete",
								method: this.delete,
								confirm: this.translate("Are you sure you want to delete this setting?")
							}
						],
						data: data,
						columns: [
							{
								title: this.translate("Setting Name"),
								key: "name"
							},
							{
								title: this.translate("ID"),
								key: "id"
							},
							{
								title: this.translate("Type"),
								key: "type"
							}
						]
					}
				];
			}
		},
		methods: {
			delete: async function(id) {
				let response = await BigTreeAPI.call({
					endpoint: "settings/delete",
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
						context: this.translate("Settings"),
						message: this.translate("Deleted Setting")
					};
					
					this.$asyncComputed.tables.update();
				}
			}
		},
		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "settings") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<grouped-tables searchable="true" escaped_data="true" search_placeholder="Search Settings"
				   search_label="Search Settings" :tables="tables"></grouped-tables>
</template>