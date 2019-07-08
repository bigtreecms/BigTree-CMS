<script>
	Vue.component("page-module-listing", {
		asyncComputed: {
			async tables () {
				let modules = await BigTreeAPI.getStoredData("modules");
				let groups = await BigTreeAPI.getStoredData("module-groups");
				let grouped_modules = {
					"ungrouped": {"name": "Ungrouped", "modules": []}
				};

				for (let x = 0; x < groups.length; x++) {
					grouped_modules[groups[x].id] = {
						"name": groups[x].name,
						"modules": []
					};
				}

				for (let x = 0; x < modules.length; x++) {
					let module = modules[x];

					if (module.group && typeof grouped_modules[module.group] !== "undefined") {
						grouped_modules[module.group].modules.push(module);
					} else {
						grouped_modules["ungrouped"].modules.push(module);
					}
				}

				let tables = [];

				for (let key in grouped_modules) {
					if (grouped_modules.hasOwnProperty(key)) {
						let group = grouped_modules[key];

						if (group.modules.length) {
							tables.push({
								"title": group.name,
								"columns": [
									{ title: "Module Name", key: "name" }
								],
								"actions": [],
								"data": group.modules
							});
						}
					}
				}

				return tables;
			}
		}
	});
</script>

<template>
	<grouped-tables collapsible="true" searchable="true" escaped_content="true" search_placeholder="Search Modules"
					search_label="Search Modules" :tables="tables"></grouped-tables>
</template>