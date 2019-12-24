<script>
	Vue.component("TagsList", {
		asyncComputed: {
			async data () {
				return await BigTreeAPI.getStoredData("tags", "tag");
			}
		},
		
		data: function() {
			return {
				actions: [
					{
						title: this.translate("Merge Tags"),
						route: "merge"
					},
					{
						title: this.translate("Delete"),
						route: "delete",
						method: this.delete,
						confirm: this.translate("Are you sure you want to delete this tag?")
					}
				],
				columns: [
					{
						title: this.translate("Tag Name"),
						key: "tag",
						sort: true
					}
				]
			}
		},
		
		methods: {
			delete: async function(id) {
				let response = await BigTreeAPI.call({
					endpoint: "tags/delete",
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
						context: this.translate("Tags"),
						message: this.translate("Deleted Tag")
					};
				}
			}
		},
		
		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "tags") {
					this.$asyncComputed.data.update();
				}
			});
		}
	});
</script>

<template>
	<div class="component layout_expanded">
		<table-sortable actions_base_path="tags" per_page="10" :columns="columns" :actions="actions"
						:data="data" escaped_data="true"></table-sortable>
	</div>
</template>