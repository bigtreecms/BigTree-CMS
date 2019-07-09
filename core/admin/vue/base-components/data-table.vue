<script>
	Vue.component("data-table", {
		props: [
			"actions",
			"actions_base_path",
			"columns",
			"data",
			"data_contains_actions",
			"draggable",
			"escaped_data"
		]
	});
</script>

<template>
	<table class="table">
		<thead class="table_headings">
			<tr class="table_headings_row">
				<th v-for="column in columns" class="table_heading">{{ column.title }}</th>
				<th v-if="actions.length" class="table_heading">Actions</th>
			</tr>
		</thead>
		<tbody class="table_body">
			<tr v-for="row in data" class="table_body_row" :draggable="draggable ? true : false">
				<td v-for="(column, index) in columns" class="table_column">
					<span v-if="column.type != 'image'" class="table_column_label">{{ column.title }}</span>
					<span class="table_column_content">
						<icon v-if="draggable && index == 0" wrapper="table_column_drag" icon="drag_handle"></icon>
						<img v-if="column.type == 'image'" class="table_column_image" :src="row[column.key]" alt="" />
						<span v-else-if="column.type == 'status'" class="table_column_status"
							  :class="'table_column_status_' + row[column.key].toLowerCase()"></span>
						<span v-else-if="escaped_data" class="table_column_text" v-html="row[column.key]"></span>
						<span v-else class="table_column_text">{{ row[column.key] }}</span>
					</span>
				</td>
				<td v-if="actions.length || data_contains_actions" class="table_column">
					<div class="table_column_content">
						<action-menu v-if="data_contains_actions && row['actions'].length" :base_path="row['actions_base_path']"
									 :actions="row['actions']" :id="row['id']"></action-menu>
						<action-menu v-else-if="actions.length" :base_path="actions_base_path" :actions="actions"
									 :id="row['id']"></action-menu>
						<span v-else>&nbsp;</span>
					</div>
				</td>
			</tr>
		</tbody>
	</table>
</template>