<script>
	Vue.component("SettingsList", {
		asyncComputed: {
			async data () {
				return await BigTreeAPI.getStoredData("settings", "name");
			}
		},

		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "settings") {
					this.$asyncComputed.data.update();
				}
			});
		}
	});
</script>

<template>
	<div class="component layout_expanded">
		<data-table actions_base_path="settings" searchable="true" per_page="10" sortable="true" :columns="[
			{ 'title': this.translate('Setting Name'), 'key': 'name', 'sort': true, 'sort_default': 'ASC', 'width': '50%' },
			{ 'title': this.translate('Value'), 'key': 'value', 'sort': true, 'width': '50%' }
		]" :actions="[
			{ 'title': this.translate('Edit Value'), 'route': 'edit' }
		]" :data="data" escaped_data="true"></data-table>
	</div>
</template>