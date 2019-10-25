<script>
	Vue.component("DeveloperFeedList", {
		asyncComputed: {
			async tables() {
				let callouts = await BigTreeAPI.getStoredData("feeds", "name");

				return [
					{
						id: "feeds",
						actions_base_path: "developer/feeds",
						actions: [
							{
								title: this.translate("Edit"),
								route: "edit"
							},
							{
								title: this.translate("Delete"),
								route: "delete",
								method: this.delete,
								confirm: this.translate("Are you sure you want to delete this feed?")
							}
						],
						data: callouts,
						columns: [
							{
								title: this.translate("Feed Name"),
								key: "name"
							},
							{
								title: this.translate("URL"),
								key: "url"
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
					endpoint: "feeds/delete",
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
				if (store === "feeds") {
					this.$asyncComputed.tables.update();
				}
			});
		}
	});
</script>

<template>
	<GroupedTables searchable="true" escaped_data="true" search_placeholder="Search Feeds"
				   search_label="Search Feeds" :tables="tables"></GroupedTables>
</template>