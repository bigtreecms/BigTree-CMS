<script>
	Vue.component("GroupedTables", {
		props: [
			"collapsible",
			"escaped_data",
			"searchable",
			"search_label",
			"search_placeholder",
			"tables"
		],
		data: function() {
			return {
				query: ""
			}
		},
		computed: {
			filtered_tables: function() {
				if (!this.query) {
					return this.tables;
				}

				let tables = [];
				let query = this.query.toLowerCase();

				for (let i = 0; i < this.tables.length; i++) {
					let table_title = "";

					if (typeof this.tables[i].title !== "undefined") {
						table_title = this.tables[i].title.toLowerCase();
					}

					// If the group title matches, keep all the modules within it shown
					if (table_title.indexOf(query) > -1) {
						tables.push(this.tables[i]);
					} else {
						let table = {
							id: this.tables[i].id,
							title: this.tables[i].title,
							columns: this.tables[i].columns,
							actions: this.tables[i].actions,
							actions_base_path: this.tables[i].actions_base_path,
							data_contains_actions: this.tables[i].data_contains_actions,
							draggable: false,
							data: []
						};

						for (let x = 0; x < this.tables[i].data.length; x++) {
							let entry = this.tables[i].data[x];
							let ok = false;
							
							for (let key in entry) {
								if (entry.hasOwnProperty(key) && typeof entry[key] === "string") {
									let lower = entry[key].toLowerCase();
									
									if (lower.indexOf(query) > -1) {
										ok = true;
									}
								}
							}
							
							if (ok) {
								table.data.push(this.tables[i].data[x]);
							}
						}

						if (table.data.length) {
							tables.push(table);
						}
					}
				}
				
				return tables;
			}
		},
		mounted: function() {
			this.$on("search.change", function(query) {
				this.query = query;
			});
			
			this.$on("search.submit", (query) => {
				let first_action_link = $(this.$el).find(".action_menu_label").first().attr("href");
				
				if (first_action_link) {
					BigTree.request_partial(first_action_link);
				}
			});
		}
	});
</script>

<template>
	<div>
		<search v-if="searchable" :label="search_label" :placeholder="search_placeholder"></search>
		
		<toggle-block v-if="collapsible" v-for="table in filtered_tables" :title="table.title" :key="table.id"
					  :id="table.id" :escaped_title="escaped_data">
			<table-draggable v-if="table.draggable" :columns="table.columns" :escaped_data="escaped_data"
							 :data="table.data" :data_contains_actions="table.data_contains_actions"
							 :actions="table.actions" :actions_base_path="table.actions_base_path">
			</table-draggable>

			<table-simple v-else :columns="table.columns" :escaped_data="escaped_data"
							:data="table.data" :data_contains_actions="table.data_contains_actions"
							:actions="table.actions" :actions_base_path="table.actions_base_path">
			</table-simple>
		</toggle-block>

		<block v-else v-for="table in filtered_tables" class="component" :title="table.title" :key="table.id">
			<table-draggable v-if="table.draggable" :columns="table.columns" :escaped_data="escaped_data"
							 :data="table.data" :data_contains_actions="table.data_contains_actions"
							 :actions="table.actions" :actions_base_path="table.actions_base_path">
			</table-draggable>

			<table-simple v-else :columns="table.columns" :escaped_data="escaped_data"
							:data="table.data" :data_contains_actions="table.data_contains_actions"
							:actions="table.actions" :actions_base_path="table.actions_base_path">
			</table-simple>
		</block>
	</div>
</template>