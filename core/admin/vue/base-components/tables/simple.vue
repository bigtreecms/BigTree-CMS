<script>
	Vue.component("TableSimple", {
		extends: BigTreeTable
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
				<tr v-for="(row, row_index) in filtered_data" class="table_row" draggable="true" :key="row.id">
					<td v-for="(column, index) in columns" class="table_column" :class="{ 'status': column.type == 'status' }">
						<span v-if="column.type != 'image'" class="table_column_label">{{ translate(column.title) }}</span>
						<span class="table_column_content">
							<img v-if="column.type === 'image'" class="table_column_image" :src="row[column.key]" alt="" />
							<span v-else-if="column.type === 'status'" class="table_content_status"
								  :class="['table_content_status_' + row[column.key].toLowerCase(), column.tooltip_key ? 'js-tooltip' : '']" :data-tooltip-title="row[column.tooltip_key]"></span>
							<button v-else-if="clickable_rows && escaped_data" v-on:click="row_click" :data-index="row_index" class="table_content_button" v-html="row[column.key]"></button>
							<button v-else-if="clickable_rows" v-on:click="row_click" :data-index="row_index" class="table_content_button">{{ row[column.key] }}</button>
							<span v-else-if="escaped_data" class="table_column_text" v-html="row[column.key]"></span>
							<span v-else class="table_column_text">{{ row[column.key] }}</span>
						</span>
					</td>

					<td v-if="data_contains_actions || (typeof actions === 'object' && actions.length)" class="table_column">
						<div class="table_column_content">
							<action-menu v-if="data_contains_actions && typeof row['actions'] === 'object' && row['actions'].length"
										 :base_path="typeof row['actions_base_path'] !== 'undefined' ? row['actions_base_path'] : actions_base_path"
										 :actions="row['actions']" :id="row['id']" :escaped_actions="escaped_data"></action-menu>
							<action-menu v-else-if="typeof actions === 'object' && actions.length"
										 :base_path="actions_base_path" :actions="actions"
										 :id="row['id']" :escaped_actions="escaped_data"></action-menu>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</template>