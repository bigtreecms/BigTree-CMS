<script>
	Vue.component("FieldTypeList", {
		asyncComputed: {
			async tables() {
				let callouts = await BigTreeAPI.getStoredData("field-types", "name");

				return [
					{
						id: "field-types",
						actions_base_path: "developer/field-types",
						actions: [
							{
								title: this.translate("Edit"),
								route: "edit"
							},
							{
								title: this.translate("Delete"),
								route: "delete",
								method: this.delete,
								confirm: this.translate("Are you sure you want to delete this field type?")
							}
						],
						data: callouts,
						columns: [
							{
								title: this.translate("Field Type Name"),
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
					endpoint: "field-types/delete",
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
				if (store === "callouts") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<GroupedTables searchable="true" escaped_data="true" search_placeholder="Search Field Types"
				   search_label="Search Field Types" :tables="tables"></GroupedTables>
</template>