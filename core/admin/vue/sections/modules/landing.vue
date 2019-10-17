<script>
	Vue.component("ModulesLanding", {
		asyncComputed: {
			async tables () {
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

					for (let action_index = 0; action_index < module.actions.length; action_index++) {
						let action = module.actions[action_index];

						if (action.in_nav) {
							if (action.route) {
								module.actions[action_index].url = ADMIN_ROOT + module.route + "/" + action.route + "/";
							} else {
								module.actions[action_index].url = ADMIN_ROOT + module.route + "/";
							}

							module.actions[action_index].title = action.name;
						} else {
							module.actions.splice(action_index, 1);
						}
					}

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
									{ title: "Module Name", key: "name" }
								],
								actions: [],
								data: group.modules,
								data_contains_actions: true
							});
						}
					}
				}

				return tables;
			}
		},
		
		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "module-groups" || store === "modules") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<GroupedTables collapsible="true" searchable="true" escaped_data="true" search_placeholder="Search Modules"
					search_label="Search Modules" :tables="tables"></GroupedTables>
</template>