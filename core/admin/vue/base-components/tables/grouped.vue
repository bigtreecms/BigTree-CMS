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
			filtered_data: function() {
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

				<draggable draggable=".table_row" handle=".table_content_drag_icon" v-on:change="resorted(group_index, $event)" tag="tbody" v-model="group.data">
					<template v-for="(row, row_index) in group.data">
						<table-row :draggable="draggable ? true : false" :key="row.id" :row="row" :columns="columns" :index="row_index"
								   :query="query" :actions_base_path="actions_base_path" :actions="actions"
								   :clickable_rows="clickable_rows" :row_click="row_click"
								   :escaped_data="escaped_data" :data_contains_actions="data_contains_actions"></table-row>
					</template>
				</draggable>
			</template>
		</table>
	</form>
</template>