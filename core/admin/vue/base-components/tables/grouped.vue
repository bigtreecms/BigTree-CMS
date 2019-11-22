<script>
	Vue.component("TableGrouped", {
		extends: BigTreeTable,
		props: [
			"draggable",
			"groups",
			"group_by",
			"view_cache_sort"
		],

		data: function() {
			return {
				grouped_data: []
			};
		},

		watch: {
			data: function() {
				this.recalculate_grouped_data();
			}
		},

		methods: {
			recalculate_grouped_data: function() {
				const data = this.filtered_data;
				let groups = {};

				// If groups were provided, they're already in the right order
				if (this.groups && this.object_length(this.groups)) {
					for (let index in this.groups) {
						if (this.groups.hasOwnProperty(index)) {
							groups[index] = { title: this.groups[index], data: [] };
						}
					}
				}

				for (let i = 0; i < data.length; i++) {
					// Allow sub-views to determine what actions each row should get
					if (this.action_calculator) {
						data[i].actions = this.action_calculator(data[i]);
					}

					let group = data[i][this.group_by];

					if (typeof groups[group] === "undefined") {
						groups[group] = { title: group, data: [] };
					}

					groups[group].data.push(data[i]);
				}

				// If groups weren't provided, we should sort the object by index to get alphabetical groups
				if (!this.groups || !this.object_length(this.groups)) {
					groups = Object.keys(groups).sort().reduce((a, c) => (a[c] = groups[c], a), {});
				}

				this.grouped_data = groups;
			},

			resorted: function(group_index, prop) {
				BigTreeEventBus.$emit("data-table-resorted", { group_index: group_index, context: this });
			}
		},

		mounted: function() {
			this.recalculate_grouped_data();
		}
	});
</script>

<template>
	<form class="component_body">
		<div class="table_filters">
			<div class="table_filter">
				<div class="search">
					<icon wrapper="search" icon="search"></icon>
					<label class="search_label" :for="id + '_query_input'">{{ translate(search_label ? search_label : 'Search') }}</label>
					<input class="search_input" :id="id + '_query_input'" type="search" autocomplete="off"
						   :placeholder="translate(search_placeholder ? search_placeholder : 'Search')"
						   v-on:keyup="query_key_up" v-model="query_field_value" />
					<input class="search_submit" type="submit" value="submit" />
				</div>
			</div>
		</div>

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

			<template v-for="(group, group_index) in grouped_data">
				<tr class="table_row" v-if="group.data.length">
					<th class="table_colspan" :colspan="columns.length + 1">{{ group.title }}</th>
				</tr>

				<draggable draggable=".table_row" handle=".table_column_drag_icon" v-on:change="resorted(group_index, $event)" tag="tbody" v-model="group.data">
					<tr v-for="(row, row_index) in group.data" class="table_row" :draggable="draggable ? true : false" :key="row.id">
						<td v-for="(column, index) in columns" class="table_column" :class="{ 'status': column.type == 'status' }">
							<span v-if="column.type != 'image'" class="table_column_label">{{ translate(column.title) }}</span>
							<span class="table_column_content">
								<icon v-if="draggable && !query && index === 0" wrapper="table_column_drag" icon="drag_handle"></icon>
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
				</draggable>
			</template>
		</table>
	</form>
</template>