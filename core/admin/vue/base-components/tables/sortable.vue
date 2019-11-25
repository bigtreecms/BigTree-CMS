<script>
	Vue.component("TableSortable", {
		extends: BigTreeTable,
		props: [
			"per_page"
		],

		data: function() {
			return {
				current_page: 1
			};
		},

		computed: {
			pages: function() {
				let count = Math.ceil(this.filtered_data.length / parseInt(this.per_page));

				return count ? count : 1;
			},

			paged_data: function() {
				const data = this.filtered_data;
				const start = (this.current_page - 1) * parseInt(this.per_page);
				let paged_data;

				// Async tables may not yet have data
				if (!data || !data.length) {
					return [];
				}

				if (this.per_page) {
					paged_data = data.slice(start, start + parseInt(this.per_page));
				} else {
					paged_data = data;
				}

				// Allow sub-views to determine what actions each row should get
				if (this.action_calculator) {
					for (let i = 0; i < paged_data.length; i++) {
						paged_data[i].actions = this.action_calculator(paged_data[i]);
					}
				}

				return paged_data;
			}
		},

		methods: {
			next_page: function(event) {
				event.preventDefault();

				if (this.current_page === this.pages) {
					return;
				}

				this.current_page++;
			},

			previous_page: function(event) {
				event.preventDefault();

				if (this.current_page === 1) {
					return;
				}

				this.current_page--;
			},

			select_page: function(event) {
				this.current_page = parseInt($(event.target).val());
			},

			sort: function(column, event) {
				event.preventDefault();

				if (this.sort_column === column) {
					if (this.sort_direction === "ASC") {
						this.sort_direction = "DESC";
					} else {
						this.sort_direction = "ASC";
					}
				} else {
					this.sort_direction = "ASC";
					this.sort_column = column;
				}

				this.sort_data();
			}
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

			<div class="table_filter" v-if="per_page && pages > 1">
				<div class="pagination">
					<button v-on:click="previous_page" :class="{'pagination_traversal_disabled': current_page === 1 }"
							class="pagination_traversal">
						<icon wrapper="pagination_traversal" icon="keyboard_arrow_left"></icon>
					</button>

					<div class="pagination_field">
						<label class="search_label" :for="id + '_pagination_select'">{{ translate('Switch Page') }}</label>
						<select v-on:change="select_page" class="pagination_select" :id="id + '_pagination_select'">
							<option v-for="i in pages" :value="i" :selected="current_page === i">Page {{ i }}</option>
						</select>
						<icon wrapper="pagination_field" icon="arrow_drop_down"></icon>
					</div>

					<button v-on:click="next_page" :class="{'pagination_traversal_disabled': current_page === pages }"
							class="pagination_traversal">
						<icon wrapper="pagination_traversal" icon="keyboard_arrow_right"></icon>
					</button>
				</div>
			</div>
		</div>

		<table class="table">
			<thead class="table_headings">
				<tr class="table_headings_row">
					<th v-for="column in columns" :class="{ 'table_heading_status': column.type === 'status' }"
						class="table_heading" :style="{ 'width': column.width ? column.width : null }">
						<button v-if="column.sort" class="table_sort" v-on:click="sort(column.key, $event)">
							<span class="table_sort_label">{{ translate(column.title) }}</span>
							<icon v-if="(!sort_column && column.sort_default) || sort_column === column.key" icon="sort"
								  :wrapper="((!sort_direction || sort_direction === 'ASC') ? 'flipped ' : '') + 'table_sort_icon_direction table_sort'">
							</icon>
						</button>

						<template v-else>
							{{ translate(column.title) }}
						</template>
					</th>
					<th v-if="data_contains_actions || (typeof actions === 'object' && actions.length)" class="table_heading table_heading_actions">
						{{ translate('Actions') }}
					</th>
				</tr>
			</thead>

			<tbody class="table_body">
				<tr v-for="(row, row_index) in paged_data" class="table_row" :key="row.id">
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

		<div class="table_filter table_filter_standalone" v-if="per_page && pages > 1">
			<div class="pagination table_filter_standalone">
				<button v-on:click="previous_page" :class="{'pagination_traversal_disabled': current_page == 1 }"
						class="pagination_traversal">
					<icon wrapper="pagination_traversal" icon="keyboard_arrow_left"></icon>
				</button>
				<div class="pagination_field">
					<label class="search_label" :for="id + '_pagination_select'">{{ translate('Switch Page') }}</label>
					<select v-on:change="select_page" class="pagination_select" :id="id + '_pagination_select'">
						<option v-for="i in pages" :value="i" :selected="current_page == i">Page {{ i }}</option>
					</select>
					<icon wrapper="pagination_field" icon="arrow_drop_down"></icon>
				</div>
				<button v-on:click="next_page" :class="{'pagination_traversal_disabled': current_page == pages }"
						class="pagination_traversal">
					<icon wrapper="pagination_traversal" icon="keyboard_arrow_right"></icon>
				</button>
			</div>
		</div>
	</form>
</template>