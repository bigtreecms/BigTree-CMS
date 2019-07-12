<script>
	Vue.component("page-pages-listing", {
		data: function() {
			return {
				page: this.current_page ? parseInt(this.current_page) : 0
			}
		},
		props: ["current_page"],
		asyncComputed: {
			async data () {
				let pages = await BigTreeAPI.getStoredDataMatching("pages", "parent", this.page);

				return pages;
			}
		},
		computed: {
			visible_pages: function() {
				if (!this.data) {
					return [];
				}
				
				let pages = [];
				
				for (let x = 0; x < this.data.length; x++) {
					let page = this.data[x];
					
					if (!page.archived && page.in_nav) {
						pages.push(page);
					}
				}

				pages.sort(function(a, b) {
					const a_position = parseInt(a.position);
					const b_position = parseInt(b.position);
					
					if (a_position === b_position) {
						return 0;
					}

					return (a_position > b_position) ? -1 : 1;
				});
				
				return pages;
			},
			hidden_pages: function() {
				if (!this.data) {
					return [];
				}

				let pages = [];

				for (let x = 0; x < this.data.length; x++) {
					let page = this.data[x];

					if (!page.archived && !page.in_nav) {
						pages.push(page);
					}
				}

				pages.sort(function(a, b) {
					const a_title = a.nav_title.toLowerCase();
					const b_title = b.nav_title.toLowerCase();

					if (a_title === b_title) {
						return 0;
					}

					return (a_title < b_title) ? -1 : 1;
				});

				return pages;
			},
			archived_pages: function() {
				if (!this.data) {
					return [];
				}

				let pages = [];

				for (let x = 0; x < this.data.length; x++) {
					let page = this.data[x];

					if (page.archived) {
						pages.push(page);
					}
				}
				
				pages.sort(function(a, b) {
					const a_title = a.nav_title.toLowerCase();
					const b_title = b.nav_title.toLowerCase();
					
					if (a_title === b_title) {
						return 0;
					}
					
					return (a_title < b_title) ? -1 : 1;
				});

				return pages;
			}
		}
	});
</script>

<template>
	<div>
		<toggle-block title="Visible in Navigation" :id="'pages-visible-' + page">
			<data-table :data="visible_pages" :columns="[
				{ 'title': 'Title', 'key': 'nav_title' }
			]" :actions="[]" escaped_data="true"></data-table>
		</toggle-block>
	
		<toggle-block title="Hidden from Navigation" :id="'pages-hidden-' + page">
			<data-table :data="hidden_pages" :columns="[
				{ 'title': 'Title', 'key': 'nav_title' }
			]" :actions="[]" escaped_data="true"></data-table>
		</toggle-block>
	
		<toggle-block title="Archived" :id="'pages-archived-' + page">
			<data-table :data="archived_pages" :columns="[
				{ 'title': 'Title', 'key': 'nav_title' }
			]" :actions="[]" escaped_data="true"></data-table>
		</toggle-block>
	</div>
</template>