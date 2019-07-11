<script>
	Vue.component("page-settings-listing", {
		asyncComputed: {
			async data () {
				let settings = await BigTreeAPI.getStoredData("settings", "title");

				return settings;
			}
		}
	});
</script>

<template>
	<div class="component layout_expanded">
		<data-table actions_base_path="settings" searchable="true" per_page="10" :columns="[
			{ 'title': 'Name', 'key': 'title' },
			{ 'title': 'Value', 'key': 'value' }
		]" :actions="[
			{ 'title': 'Edit Value', 'route': 'edit' }
		]" :data="data" escaped_data="true"></data-table>
	</div>
</template>