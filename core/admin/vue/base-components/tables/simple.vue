<script>
	Vue.component("TableSimple", {
		extends: BigTreeTable,
		computed: {
			data_with_actions: function() {
				const data = this.filtered_data;

				// Allow sub-views to determine what actions each row should get
				if (this.action_calculator) {
					for (let i = 0; i < data.length; i++) {
						data[i].actions = this.action_calculator(data[i]);
					}
				}

				return data;
			}
		}
	});
</script>

<template>
	<form class="component_body">
		<table class="table">
			<thead class="table_headings">
				<tr class="table_headings_row">
					<th v-for="column in columns" :class="{ 'table_heading_status': column.type === 'status' }"
						class="table_heading" :style="{ 'width': column.width ? column.width : null }">
						{{ translate(column.title) }}
					</th>
					<th v-if="data_contains_actions || (typeof actions === 'object' && actions.length)" class="table_heading table_heading_actions">{{ translate('Actions') }}</th>
				</tr>
			</thead>

			<tbody class="table_body">
				<template v-for="(row, row_index) in data_with_actions">
					<table-row :key="row.id" :row="row" :columns="columns" :index="row_index"
							   :query="query" :actions_base_path="actions_base_path" :actions="actions"
							   :clickable_rows="clickable_rows" :row_click="row_click"
							   :escaped_data="escaped_data" :data_contains_actions="data_contains_actions"></table-row>
				</template>
			</tbody>
		</table>
	</form>
</template>