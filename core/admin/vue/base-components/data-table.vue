<script>
	Vue.component("data-table", {
		props: [
			"actions",
			"actions_base_path",
			"clickable_rows",
			"columns",
			"data",
			"data_contains_actions",
			"draggable",
			"escaped_data",
			"per_page",
			"searchable",
			"search_label",
			"search_placeholder",
			"translatable"
		],
		data: function() {
			return {
				current_page: 1,
				id: null,
				pages: 1,
				query: "",
				query_field_value: "",
				query_timer: null
			};
		},
		computed: {
			filtered_data: function() {
				let data = this.data;
				
				if (!data || !data.length) {
					return [];
				}

				if (this.searchable && this.query) {
					data = [];
					let query = this.query.toLowerCase();

					for (let x = 0; x < this.data.length; x++) {
						let entry = this.data[x];

						for (let index = 0; index < this.columns.length; index++) {
							let column = entry[this.columns[index].key].toLowerCase();

							if (column.indexOf(query) > -1) {
								data.push(entry);
							}
						}
					}
				}

				this.pages = Math.ceil(data.length / parseInt(this.per_page));

				if (!this.pages) {
					this.pages = 1;
				}
				
				if (this.searchable) {
					$(this.$el).find(".search").removeClass("loading");
				}
				
				return data;
			},
			paged_data: function() {
				const data = this.filtered_data;
				const start = (this.current_page - 1) * parseInt(this.per_page);
				
				// Async tables may not yet have data
				if (!data || !data.length) {
					return [];
				}
				
				if (!this.per_page) {
					return data;
				}
				
				return data.slice(start, start + parseInt(this.per_page));
			}
		},
		methods: {
			next_page: function(event) {
				event.preventDefault();
				
				if (this.current_page == this.pages) {
					return;
				}
				
				this.current_page++;
			},
			previous_page: function(event) {
				event.preventDefault();

				if (this.current_page == 1) {
					return;
				}
				
				this.current_page--;
			},
			query_key_up: function() {
				$(this.$el).find(".search").addClass("loading");
				
				if (this.query_timer) {
					clearTimeout(this.query_timer);
				}
				
				this.query_timer = setTimeout(this.query_parse, 500);
			},
			query_parse: function() {
				this.query = this.query_field_value;
			},
			row_click: function(event, data) {
				event.preventDefault();

				const index = $(event.target).data("index");
				this.$emit("row-click", this.paged_data[index]);
			},
			select_page: function(event) {
				this.current_page = parseInt($(event.target).val());
			}
		},
		mounted: function() {
			this.id = this._uid;
		}
	});
</script>

<template>
	<form class="component_body">
		<div class="table_filters" v-if="searchable || (per_page && pages > 1)">
			<div class="table_filter" v-if="searchable">
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
		</div>
		
		<table class="table">
			<thead class="table_headings">
				<tr class="table_headings_row">
					<th v-for="column in columns" :class="{ 'table_heading_status': column.type == 'status' }" class="table_heading">{{ translate(column.title) }}</th>
					<th v-if="actions.length || data_contains_actions" class="table_heading table_heading_actions">{{ translate('Actions') }}</th>
				</tr>
			</thead>
			<tbody class="table_body">
				<tr v-for="(row, row_index) in paged_data" class="table_body_row" :draggable="draggable ? true : false">
					<td v-for="(column, index) in columns" class="table_column" :class="{ 'status': column.type == 'status' }">
						<span v-if="column.type != 'image'" class="table_column_label">{{ translate(column.title) }}</span>
						<span class="table_column_content">
							<icon v-if="draggable && index == 0" wrapper="table_column_drag" icon="drag_handle"></icon>
							<img v-if="column.type == 'image'" class="table_column_image" :src="row[column.key]" alt="" />
							<span v-else-if="column.type == 'status'" class="table_content_status"
								  :class="'table_content_status_' + row[column.key].toLowerCase()"></span>
							<button v-else-if="clickable_rows && escaped_data" v-on:click="row_click" :data-index="row_index" class="table_column_button" v-html="row[column.key]"></button>
							<button v-else-if="clickable_rows" v-on:click="row_click" :data-index="row_index" class="table_column_button">{{ row[column.key] }}</button>
							<span v-else-if="escaped_data" class="table_column_text" v-html="row[column.key]"></span>
							<span v-else class="table_column_text">{{ row[column.key] }}</span>
						</span>
					</td>
					<td v-if="actions.length || data_contains_actions" class="table_column">
						<div class="table_column_content">
							<action-menu v-if="data_contains_actions && row['actions'].length" :base_path="row['actions_base_path']"
										 :actions="row['actions']" :id="row['id']" :escaped_actions="escaped_data"></action-menu>
							<action-menu v-else-if="actions.length" :base_path="actions_base_path" :actions="actions"
										 :id="row['id']" :escaped_actions="escaped_data"></action-menu>
							<span v-else>&nbsp;</span>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
		
		<div class="single_table_filter" v-if="per_page && pages > 1">
			<div class="table_filter">
				<div class="pagination">
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
		</div>
	</form>
</template>