<script>
	Vue.component("DeveloperExtensionList", {
		asyncComputed: {
			async tables() {
				let data = await BigTreeAPI.getStoredData("extensions", "name");

				return [
					{
						id: "settings",
						actions_base_path: "developer/extensions",
						actions: [
							{
								title: this.translate("Edit"),
								route: "edit"
							},
							{
								title: this.translate("Uninstall"),
								route: "delete",
								method: this.delete,
								confirm: this.translate("Are you sure you want to uninstall this extension?\n\n" +
									"Related components, including those that were added to this package will also completely deleted (including related files).")
							}
						],
						data: data,
						columns: [
							{
								title: this.translate("Extension Name"),
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
					endpoint: "extensions/delete",
					method: "POST",
					parameters: {
						id: id
					}
				});

				if (response.error) {
					BigTree.notification = {
						type: "error",
						context: this.translate("Uninstall Failed"),
						message: this.translate(response.message)
					};
				} else {
					BigTree.notification = {
						type: "success",
						context: this.translate("Extensions"),
						message: this.translate("Uninstalled Extension")
					};
				}

				this.$asyncComputed.tables.update();
			}
		},
		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "extensions") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<grouped-tables searchable="true" escaped_data="true" search_placeholder="Search Extensions"
				   search_label="Search Extensions" :tables="tables"></grouped-tables>
</template>