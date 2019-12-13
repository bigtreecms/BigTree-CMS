<script>
	Vue.component("TableRow", {
		props: [
			"actions",
			"actions_base_path",
			"clickable_rows",
			"columns",
			"data_contains_actions",
			"depth",
			"draggable",
			"escaped_data",
			"expand_collapse",
			"index",
			"query",
			"row",
			"row_click"
		],
		
		methods: {
			prefix_file: function(file, prefix) {
				if (typeof prefix === "undefined") {
					return file;
				}

				let parts = file.split("/");

				parts[parts.length - 1] = prefix + parts[parts.length - 1];

				return parts.join("/");
			},

			expand: function($event, row, index) {
				$event.preventDefault();

				BigTreeEventBus.$emit("table-expand-collapse", row, index);
			}
		}
	});
</script>

<template>
	<tr class="table_row" :draggable="draggable" :key="row.id" :data-level="depth">
		<td v-for="(column, column_index) in columns" class="table_column" :class="{ 'status': column.type === 'status', 'image': column.type === 'image' }">
			<span v-if="column.type !== 'image'" class="table_column_label">{{ translate(column.title) }}</span>

			<span class="table_column_content">
				<icon v-if="draggable && !query && column_index === 0" wrapper="table_content_drag" icon="drag_handle"></icon>

				<button v-if="row.depth && column_index === 0" v-on:click="expand($event, row, index)" class="component_expander" :class="{'disabled': !row.has_children}">
					<icon wrapper="component_expander" icon="expand_more"></icon>
					<icon wrapper="component_expander" icon="expand_less"></icon>
				</button>

				<img v-if="column.type === 'image'" class="table_content_image" :src="prefix_file(row[column.key], column.prefix)" alt="" />

				<span v-else-if="column.type === 'status'" class="table_content_status"
					  :class="['table_content_status_' + row[column.key].toLowerCase(), column.tooltip_key ? 'js-tooltip' : '']" :data-tooltip-title="row[column.tooltip_key]"></span>

				<button v-else-if="clickable_rows && escaped_data" v-on:click="row_click" :data-index="index" class="table_content_button" v-html="row[column.key]"></button>

				<button v-else-if="clickable_rows" v-on:click="row_click" :data-index="index" class="table_content_button">{{ row[column.key] }}</button>

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
</template>