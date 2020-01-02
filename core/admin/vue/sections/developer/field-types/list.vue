<script>
	Vue.component("DeveloperFieldTypeList", {
		asyncComputed: {
			async tables() {
				let data = await BigTreeAPI.getStoredData("field-types", "name");

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
						data: data,
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
				let response = await BigTreeAPI.call({
					endpoint: "field-types/delete",
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
						context: this.translate("Field Types"),
						message: this.translate("Deleted Field Type")
					};
				}

				this.$asyncComputed.tables.update();
			}
		},
		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "field-types") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<grouped-tables searchable="true" escaped_data="true" search_placeholder="Search Field Types"
				   search_label="Search Field Types" :tables="tables"></grouped-tables>
</template>