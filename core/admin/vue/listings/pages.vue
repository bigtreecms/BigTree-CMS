<script>
	Vue.component("page-pages-listing", {
		data: function() {
			return {
				page: this.current_page ? parseInt(this.current_page) : 0
			}
		},
		props: ["current_page"],
		asyncComputed: {
			async current_page_data () {
				let data = await BigTreeAPI.getStoredDataMatching("pages", "id", this.page);

				if (!data.length) {
					return;
				}
				
				let page = data[0];

				if (page.path) {
					app.page_public_url = WWW_ROOT + page.path + "/";
				} else {
					app.page_public_url = WWW_ROOT;
				}

				app.page_title = page.nav_title;

				let breadcrumb = [];
				let parent = page.parent;

				while (parent > -1) {
					let parent_data = await BigTreeAPI.getStoredDataMatching("pages", "id", parent);

					if (parseInt(parent_data[0].id) === 0) {
						breadcrumb.push({ title: "Home", url: '#', id: 0 });
					} else {
						breadcrumb.push({ title: parent_data[0].nav_title, url: '#', id: parent_data[0].id });
					}

					parent = parent_data[0].parent;
				}

				app.breadcrumb = breadcrumb.reverse();
				
				meta_bar = [];

				if (page.expires) {
					meta_bar.push({
						title: "Expires",
						value: page.expires
					})
				}
				
				if (page.seo_score) {
					meta_bar.push({
						title: "SEO Score",
						type: "visual",
						value: parseInt(page.seo_score)
					});
				}
				
				console.log(page.max_age, page.age);
				
				meta_bar.push({
					title: "Content Age",
					type: "visual",
					value: 100 - Math.floor(page.max_age / page.age)
				});
				
				console.log(meta_bar);
				
				app.meta_bar = meta_bar;
			},
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
		},
		methods: {
			navigate: function(data) {
				this.page = data.id;
			}
		},
		mounted: function() {
			VueEventBus.$on("breadcrumb-click", (id) => {
				this.navigate({ id: id });
			});
		}
	});
</script>

<template>
		<alert v-if="!visible_pages.length && !hidden_pages.length && !archived_pages.length" type="notice" title="No Subpages Yet">
			There are currently no subpages.<br>
			• Create a subpage by clicking the &ldquo;Add Subpage&rdquo; button.<br>
			• Edit this page by clicking the &ldquo;Content&rdquo; tab.
		</alert>

		<div v-else>
			<toggle-block title="Visible in Navigation" :id="'pages-visible-' + page">
				<data-table :data="visible_pages" v-on:row-click="navigate" :columns="[
					{ 'title': 'Title', 'key': 'nav_title' },
					{ 'title': 'Status', 'key': 'status', 'type': 'status' }
				]" :actions="[]" escaped_data="true" clickable_rows="true"></data-table>
			</toggle-block>
	
			<toggle-block title="Hidden from Navigation" :id="'pages-hidden-' + page">
				<data-table :data="hidden_pages" v-on:row-click="navigate" :columns="[
					{ 'title': 'Title', 'key': 'nav_title' },
					{ 'title': 'Status', 'key': 'status', 'type': 'status' }
				]" :actions="[]" escaped_data="true" clickable_rows="true"></data-table>
			</toggle-block>
	
			<toggle-block title="Archived" :id="'pages-archived-' + page">
				<data-table :data="archived_pages" :columns="[
					{ 'title': 'Title', 'key': 'nav_title' },
					{ 'title': 'Status', 'key': 'status', 'type': 'status' }
				]" :actions="[]" escaped_data="true"></data-table>
			</toggle-block>
		</div>
</template>