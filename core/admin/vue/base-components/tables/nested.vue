<script>
	Vue.component("TableNested", {
		extends: BigTreeTable,
		props: [
			"nesting_column",
			"no_search"
		],
		data: function() {
			return {
				current_drag_children: []
			};
		},
		computed: {
			parsed_data: function() {
				const data = this.filtered_data;

				// Allow sub-views to determine what actions each row should get
				if (this.action_calculator) {
					for (let i = 0; i < data.length; i++) {
						data[i].actions = this.action_calculator(data[i]);
					}
				}

				return this.get_children(data, 0, 1);
			}
		},
		methods: {
			drag_end: function(ev) {
				// Restore the collapsed state of the child elements
				$.each(this.current_drag_children, function(key, value) {
					if (!value.data("previously-collapsed")) {
						value.removeClass("collapsed");
					}
				});
				
				this.current_drag_children = [];
			},
			
			drag_start: function(ev) {
				const row = $(ev.item);
				const lower = this.get_lower_rows(row);
				
				// Collapse all the child elements when we start dragging
				$.each(lower, function(key, value) {
					value.data("previously-collapsed", value.hasClass("collapsed"));
					value.addClass("collapsed");
				});
				
				this.current_drag_children = lower;
			},
			
			expand_collapse: function(data, index) {
				const row = $(this.$el).find(".table_row").eq(index);
				const button = row.find(".component_expander");
				const currently_collapsed = button.hasClass("collapsed");
				let lower = this.get_lower_rows(row);
				
				for (let i = 0; i < lower.length; i++) {
					if (currently_collapsed) {
						lower[i].removeClass("collapsed");
					} else {
						lower[i].addClass("collapsed");
					}
				}
				
				button.toggleClass("collapsed");
			},

			get_children: function(data, parent_id, depth) {
				let level = [];

				for (let i = 0; i < data.length; i++) {
					let item = data[i];

					if (parseInt(item[this.nesting_column]) === parseInt(parent_id)) {
						let children = this.get_children(data, item.id, depth + 1);
						item.depth = depth;

						if (children.length) {
							item.has_children = true;
						}

						level.push(item);
						level = level.concat(children);

					}
				}

				return level;
			},
			
			get_lower_rows: function(row) {
				const rows = $(this.$el).find(".table_row");
				const this_row_depth = row.data("level");
				const index = rows.index(row);
				let lower = [];

				for (let i = index + 1; i < rows.length; i++) {
					let level = rows.eq(i).data("level");
					
					if (level > this_row_depth) {
						lower.push(rows.eq(i));
					} else if (level === this_row_depth) {
						break;
					}
				}
				
				return lower;
			},

			resorted: function(group_index, prop) {
				//BigTreeEventBus.$emit("data-table-resorted", this);
			}
		},
		mounted: function() {
			this.sort_column = "position";
			this.sort_direction = "DESC";

			BigTreeEventBus.$on("table-expand-collapse", this.expand_collapse);
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

			<tbody class="table_body">
				<template v-for="(row, row_index) in parsed_data">
					<table-row :key="row.id" :row="row" :columns="columns" :index="row_index"
							   :query="query" :actions_base_path="actions_base_path" :actions="actions"
							   :clickable_rows="clickable_rows" :row_click="row_click"
							   :depth="row.depth" :expand_collapse="expand_collapse"
							   :escaped_data="escaped_data" :data_contains_actions="data_contains_actions"></table-row>
				</template>
			</tbody>
		</table>
	</form>
</template>