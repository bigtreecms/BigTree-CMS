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
				let response = await BigTreeAPI.call({
					endpoint: "feeds/delete",
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
						context: this.translate("Feeds"),
						message: this.translate("Deleted Feed")
					};
				}

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
	<grouped-tables searchable="true" escaped_data="true" search_placeholder="Search Feeds"
				   search_label="Search Feeds" :tables="tables"></grouped-tables>
</template>