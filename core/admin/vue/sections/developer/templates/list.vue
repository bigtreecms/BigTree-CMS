<script>
	Vue.component("TemplateList", {
		props: ["templates"],
		data: function() {
			let actions = [
				{
					title: this.translate("Edit"),
					route: "edit"
				},
				{
					title: this.translate("Delete"),
					route: "delete",
					confirm: this.translate("Are you sure you want to delete this template?")
				}
			];
			let basic_templates = [];
			let routed_templates = [];
			
			for (let x = 0; x < this.templates.length; x++) {
				let template = this.templates[x];
				
				if (template.routed) {
					routed_templates.push(template);
				} else {
					basic_templates.push(template);
				}
			}
			
			let tables = [
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
			
			return {
				tables: tables
			}
		},
		mounted: function() {
			BigTreeEventBus.$on("data-table-resorted", async (table) => {
				let data = table.mutable_data;

				let positions = {};
				let position = data.length;

				for (let x = 0; x < data.length; x++) {
					positions[data[x].id] = position;
					position--;
				}

				await BigTreeAPI.call({
					endpoint: "templates/order",
					method: "POST",
					parameters: {
						"positions": positions
					}
				});
			});
		}
	});
</script>

<template>
	<GroupedTables collapsible="true" searchable="true" escaped_data="true" search_placeholder="Search Templates"
				   search_label="Search Templates" :tables="tables"></GroupedTables>
</template>