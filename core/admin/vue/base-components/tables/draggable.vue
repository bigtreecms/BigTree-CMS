<script>
	Vue.component("TableDraggable", {
		extends: BigTreeTable,
		props: ["no_search"],
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
		},
		methods: {
			resorted: function(group_index, prop) {
				BigTreeEventBus.$emit("data-table-resorted", this);
			}
		},
		mounted: function() {
			this.sort_column = "position";
			this.sort_direction = "DESC";
		}
	});
</script>

<template>
	<form class="component_body">
		<div class="table_filters" v-if="!no_search">
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

			<draggable v-model="mutable_data" draggable=".table_row" handle=".table_content_drag_icon"
					   v-on:change="resorted" tag="tbody" class="table_body">
				<template v-for="(row, row_index) in data_with_actions">
					<table-row draggable="true" :key="row.id" :row="row" :columns="columns" :index="row_index"
							   :query="query" :actions_base_path="actions_base_path" :actions="actions"
							   :clickable_rows="clickable_rows" :row_click="row_click"
							   :escaped_data="escaped_data" :data_contains_actions="data_contains_actions"></table-row>
				</template>
			</draggable>
		</table>
	</form>
</template>