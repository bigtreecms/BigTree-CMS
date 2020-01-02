<script>
	Vue.component("DeveloperModuleList", {
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
						confirm: this.translate("Are you sure you want to delete this module?\nDeleting a module will also delete its class file and related directory in /custom/admin/modules/.")
					}
				];
				let modules = await BigTreeAPI.getStoredData("modules", "position", true);
				let groups = await BigTreeAPI.getStoredData("module-groups", "position", true);
				let ungrouped_modules = [];
				let grouped_modules = {};

				for (let x = 0; x < groups.length; x++) {
					grouped_modules[groups[x].id] = {
						"id": groups[x].id,
						"name": groups[x].name,
						"modules": []
					};
				}

				for (let x = 0; x < modules.length; x++) {
					let module = modules[x];

					if (module.group && typeof grouped_modules[module.group] !== "undefined") {
						grouped_modules[module.group].modules.push(module);
					} else {
						ungrouped_modules.push(module);
					}
				}

				grouped_modules.ungrouped = {
					id: "ungrouped",
					name: this.translate("Ungrouped"),
					modules: ungrouped_modules
				};

				let tables = [];

				for (let key in grouped_modules) {
					if (grouped_modules.hasOwnProperty(key)) {
						let group = grouped_modules[key];

						if (group.modules.length) {
							tables.push({
								id: "module-group-" + group.id,
								title: group.name,
								columns: [
									{ title: this.translate("Module Name"), key: "name" }
								],
								actions: actions,
								actions_base_path: "developer/modules",
								data: group.modules,
								draggable: true
							});
						}
					}
				}

				return tables;
			}
		},
		methods: {
			delete: async function(id) {
				let response = await BigTreeAPI.call({
					endpoint: "modules/delete",
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
						context: this.translate("Modules"),
						message: this.translate("Deleted Module")
					};

					this.$asyncComputed.tables.update();
				}
			}
		},
		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "module-groups" || store === "modules") {
					this.$asyncComputed.tables.update();
				}
			});
			
			BigTreeEventBus.$on("data-table-resorted", async (table) => {
				let data = table.mutable_data;
				let modules = [];

				for (let x = 0; x < data.length; x++) {
					modules.push(data[x].id);
				}

				await BigTreeAPI.call({
					endpoint: "modules/order",
					method: "POST",
					parameters: {
						"modules": modules
					}
				});
			});
		}
	});
</script>

<template>
	<grouped-tables collapsible="true" searchable="true" escaped_data="true" search_placeholder="Search Modules"
				   search_label="Search Modules" :tables="tables"></grouped-tables>
</template>