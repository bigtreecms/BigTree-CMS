<script>
	Vue.component("DataTable", {
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
			"sortable",
			"translatable"
		],
		
		data: function() {
			return {
				current_page: 1,
				id: null,
				mutable_data: null,
				pages: 1,
				query: "",
				query_field_value: "",
				query_timer: null,
				sort_column: null,
				sort_direction: null
			};
		},
		
		watch: {
			data: function(new_val, old_val) {
				this.mutable_data = new_val;
			}
		},
		
		computed: {
			filtered_data: function() {
				let data = this.mutable_data ? this.mutable_data : this.data;
				
				if (!data || !data.length) {
					return [];
				}

				if (this.searchable && this.query) {
					data = [];
					let query = this.query.toLowerCase();

					for (let x = 0; x < this.mutable_data.length; x++) {
						let entry = this.mutable_data[x];

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
				
				if (this.sortable) {
					if (!this.sort_column) {
						// See if it was specified in the column configuration
						for (let index = 0; index < this.columns.length; index++) {
							let column = this.columns[index];
							
							if (column.sort_default) {
								this.sort_column = column.key;
								this.sort_direction = (column.sort_default.toLowerCase() === "asc") ? "ASC" : "DESC";
							}
						}
						
						// Wasn't specified, default to the first column
						if (!this.sort_column) {
							this.sort_column = this.columns[0].key;
							this.sort_direction = "ASC";
						}
					}
					
					data.sort((a, b) => {
						const a_val = a[this.sort_column].toLowerCase();
						const b_val = b[this.sort_column].toLowerCase();
						
						if (a_val === b_val) {
							return 0;
						}

						return (a_val < b_val) ? -1 : 1;
					});
					
					if (this.sort_direction === "DESC") {
						data.reverse();
					}
				}
				
				return data;
			},
			
			paged_data: {
				get() {
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
				},
				set(value) {
					this.mutable_data = value;
				}
			}
		},
		
		methods: {
			equalize_actions: function() {
				// Make all the action menus be equal width
				const $items = $(this.$el).find(".action_menu_item");
				const $labels = $(this.$el).find(".action_menu_label");
				const padding_left = parseInt($labels.css("padding-left"));
				const padding_right = parseInt($labels.css("padding-right"));
				
				let widest = 0;
				let unique_action_titles = [];
				
				$items.each(function() {
					let text = $(this).text();

					if (unique_action_titles.indexOf(text) === -1) {
						unique_action_titles.push(text);
					}
				});
				
				$.each(unique_action_titles, function(key, value) {
					const $tester = $('<div class="action_menu_label" style="position: absolute; left: -1000px;">').text(value);
					$("body").append($tester);

					const label_width = parseInt($tester.width());
					$tester.remove();

					if (label_width > widest) {
						widest = label_width;
					}
				});

				$labels.css({ minWidth: (widest + padding_left + padding_right) + "px" });
			},
			
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
			
			resorted: function() {
				BigTreeEventBus.$emit("data-table-resorted", this);
			},
			
			row_click: function(event, data) {
				event.preventDefault();

				const index = $(event.target).data("index");
				this.$emit("row-click", this.paged_data[index]);
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
			}
		},
		
		mounted: function() {
			// Give this table the auto-generated Vue unique ID
			this.id = this._uid;
			this.equalize_actions();
			this.mutable_data = this.data;
		},
		
		updated: function() {
			this.equalize_actions();
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
						<span v-else>
							{{ translate(column.title) }}
						</span>
					</th>
					<th v-if="data_contains_actions || (typeof actions === 'object' && actions.length)" class="table_heading table_heading_actions">{{ translate('Actions') }}</th>
				</tr>
			</thead>
			<draggable v-model="paged_data" draggable=".table_row" handle=".table_column_drag_icon"
					   v-on:change="resorted" tag="tbody" class="table_body">
				<tr v-for="(row, row_index) in paged_data" class="table_row" :draggable="draggable ? true : false" :key="row.id">
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
							<span v-else>{{ typeof row['actions'] }}&nbsp;</span>
						</div>
					</td>
				</tr>
			</draggable>
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