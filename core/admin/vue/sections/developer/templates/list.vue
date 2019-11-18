<script>
	Vue.component("DeveloperTemplateList", {
		asyncComputed: {
			async tables() {
				let actions = [
					{
						title: this.translate("Edit"),
						route: "edit"
					},
					{
						title: this.translate("Delete"),
						route: "delete",
						method: this.delete,
						confirm: this.translate("Are you sure you want to delete this template?")
					}
				];
				let basic_templates = [];
				let routed_templates = [];
				let data = await BigTreeAPI.getStoredData("templates", "position", true);
				
				for (let x = 0; x < data.length; x++) {
					let template = data[x];

					if (template.routed) {
						routed_templates.push(template);
					} else {
						basic_templates.push(template);
					}
				}

				return [
					{
						id: "basic",
						title: this.translate("Basic Templates"),
						actions_base_path: "developer/templates",
						actions: actions,
						data: basic_templates,
						draggable: true,
						columns: [
							{
								title: this.translate("Template Name"),
								key: "name"
							}
						]
					},
					{
						id: "routed",
						title: this.translate("Routed Templates"),
						actions_base_path: "developer/templates",
						actions: actions,
						draggable: true,
						data: routed_templates,
						columns: [
							{
								title: this.translate("Template Name"),
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
					endpoint: "templates/delete",
					method: "POST",
					parameters: {
						id: id
					}
				});

				this.$asyncComputed.tables.update();
			}
		},
		mounted: function() {
			BigTreeEventBus.$on("data-table-resorted", async (table) => {
				let data = table.mutable_data;
				let templates = [];

				for (let x = 0; x < data.length; x++) {
					templates.push(data[x].id);
				}

				await BigTreeAPI.call({
					endpoint: "templates/order",
					method: "POST",
					parameters: {
						"templates": templates
					}
				});
			});

			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "templates") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<grouped-tables collapsible="true" searchable="true" escaped_data="true" search_placeholder="Search Templates"
				   search_label="Search Templates" :tables="tables"></grouped-tables>
</template>