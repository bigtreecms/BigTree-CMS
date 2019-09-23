<script>
	Vue.component("SettingsList", {
		asyncComputed: {
			async data () {
				return await BigTreeAPI.getStoredData("settings", "title");
			}
		}
	});
</script>

<template>
	<div class="component layout_expanded">
		<DataTable actions_base_path="settings" searchable="true" per_page="10" sortable="true" :columns="[
			{ 'title': 'Name', 'key': 'title', 'sort': true, 'sort_default': 'ASC', 'width': '50%' },
			{ 'title': 'Value', 'key': 'value', 'sort': true, 'width': '50%' }
		]" :actions="[
			{ 'title': 'Edit Value', 'route': 'edit' }
		]" :data="data" escaped_data="true"></DataTable>
	</div>
</template>