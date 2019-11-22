<script>
	Vue.component("ModuleViewSearchable", {
		extends: BigTreeModuleView,
		props: ["sort_column", "sort_direction"]
	});
</script>

<template>
	<div class="component">
		<help-text v-if="help_text" :text="help_text"></help-text>
		<table-sortable :id="id" searchable="true" escaped_data="true" per_page="15" sortable="true"
						:data="data" :data_contains_actions="true" :columns="columns"
						:view_cache_sort="sort_direction"
						:action_calculator="action_calculator"
						:actions_base_path="actions_base_path"></table-sortable>
	</div>
</template>