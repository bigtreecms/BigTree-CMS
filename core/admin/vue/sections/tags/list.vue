<script>
	Vue.component("TagsList", {
		asyncComputed: {
			async data () {
				return await BigTreeAPI.getStoredData("tags", "tag");
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
		<DataTable actions_base_path="tags" searchable="true" sortable="true" per_page="10" :columns="[
			{ 'title': 'Tag Name', 'key': 'tag', 'sort': true }
		]" :actions="[
			{ 'title': 'Merge Tags', 'route': 'merge' },
			{ 'title': 'Delete Tag', 'route': 'delete' }
		]" :data="data" escaped_data="true"></DataTable>
	</div>
</template>