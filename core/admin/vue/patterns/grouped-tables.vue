<script>
	Vue.component("grouped-tables", {
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
					let match = false;
					let table_title = this.tables[i].title.toLowerCase();

					// If the group title matches, keep all the modules within it shown
					if (table_title.indexOf(query) > -1) {
						tables.push(this.tables[i]);
					} else {
						let table = {
							id: this.tables[i].id,
							title: this.tables[i].title,
							columns: this.tables[i].columns,
							actions: this.tables[i].actions,
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
		}
	});
</script>

<template>
	<div>
		<search v-if="searchable" :label="search_label" :placeholder="search_placeholder"></search>
		
		<toggle-block v-if="collapsible" v-for="table in filtered_tables" :title="table.title" :key="table.id"
					  :id="table.id" :escaped_title="escaped_data">
			<data-table :columns="table.columns" :actions="table.actions" :data="table.data" :escaped_data="escaped_data"></data-table>
		</toggle-block>

		<block v-else v-for="table in filtered_tables" class="component" :title="table.title" :key="table.id">
			<data-table :columns="table.columns" :actions="table.actions" :data="table.data" :escaped_data="escaped_data"></data-table>
		</block>
	</div>
</template>