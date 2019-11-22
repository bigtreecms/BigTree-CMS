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
		<table-sortable actions_base_path="tags" per_page="10" :columns="[
			{ 'title': this.translate('Tag Name'), 'key': 'tag', 'sort': true }
		]" :actions="[
			{ 'title': this.translate('Merge Tags'), 'route': 'merge' },
			{ 'title': this.translate('Delete Tag'), 'route': 'delete' }
		]" :data="data" escaped_data="true"></table-sortable>
	</div>
</template>