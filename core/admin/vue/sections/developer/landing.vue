<script>
	Vue.component("DeveloperLanding", {
		data: function() {
			let groups = {
				"Create": {
					"Templates": "templates",
					"Modules": "modules",
					"Callouts": "callouts",
					"Field Types": "field-types",
					"Feeds": "feeds",
					"Settings": "settings",
					"Extensions": "extensions"
				},
				"Configure": {
					"Cloud Storage": "cloud-storage",
					"Payment Gateway": "payment-gateway",
					"Analytics": "analytics",
					"Geocoding": "geocoding",
					"Email Delivery": "email",
					"Service APIs": "services",
					"Media Presets": "media",
					"File Metadata": "files",
					"Security": "security",
					"Dashboard": "dashboard",
					"Daily Digest & Cron": "cron-digest"
				},
				"Debug": {
					"Site Status": "status",
					"Audit Trail": "audit",
					"User Emulator": "user-emulator",
					"Content Generator": "content-generator"
				}
			};
			
			let tables = [];
			
			for (let group in groups) {
				if (groups.hasOwnProperty(group)) {
					let table = {
						id: group.toLowerCase(),
						title: group,
						columns: [
							{
								type: "text",
								key: "title",
								title: "Section"
							}
						],
						data_contains_actions: true,
						data: []
					};
				
					for (let section in groups[group]) {
						if (groups[group].hasOwnProperty(section)) {
							table.data.push({
								title: section,
								actions: [
									{
										title: "Manage",
										url: ADMIN_ROOT + "developer/" + groups[group][section] + "/"
									}
								]
							});
						}
					}
					
					tables.push(table);
				}
			}
			
			return {
				tables: tables
			}
		}
	});
</script>

<template>
	<grouped-tables collapsible="true" searchable="true" escaped_data="true" search_placeholder="Search Developer Tools"
				   search_label="Search Developer Tools" :tables="tables"></grouped-tables>
</template>