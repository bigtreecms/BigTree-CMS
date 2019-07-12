<script>
	Vue.component("page-tags-listing", {
		asyncComputed: {
			async data () {
				let tags = await BigTreeAPI.getStoredData("tags", "tag");
				
				return tags;
			}
		}
	});
</script>

<template>
	<div class="component layout_expanded">
		<data-table actions_base_path="tags" searchable="true" per_page="10" :columns="[
			{ 'title': 'Tag Name', 'key': 'tag' }
		]" :actions="[
			{ 'title': 'Merge Tags', 'route': 'merge' },
			{ 'title': 'Delete Tag', 'route': 'delete' }
		]" :data="data" escaped_data="true"></data-table>
	</div>
</template>