<script>
	Vue.component("DeveloperSettingList", {
		asyncComputed: {
			async tables() {
				let data = await BigTreeAPI.getStoredData("settings", "title");

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
								key: "title"
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
				await BigTreeAPI.call({
					endpoint: "settings/delete",
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
				if (store === "settings") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<GroupedTables searchable="true" escaped_data="true" search_placeholder="Search Settings"
				   search_label="Search Settings" :tables="tables"></GroupedTables>
</template>