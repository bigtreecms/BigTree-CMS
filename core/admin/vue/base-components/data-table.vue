<script>
	Vue.component("data-table", {
		props: ["columns", "actions", "actions_base_path", "data", "draggable"],
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
						<span v-else-if="column.type == 'status'" class="table_column_status" :class="'table_column_status_' + row[column.key].toLowerCase()"></span>
						<span v-else class="table_column_text">{{ row[column.key] }}</span>
					</span>
				</td>
				<td v-if="actions.length" class="table_column">
					<div class="table_column_content">
						<action-menu :base_path="actions_base_path" :actions="actions" :id="row['id']"></action-menu>
					</div>
				</td>
			</tr>
		</tbody>
	</table>
</template>