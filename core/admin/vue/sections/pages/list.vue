<script>
	Vue.component("PagesList", {
		data: function() {
			return {
				page: this.current_page ? parseInt(this.current_page) : 0,
				can_publish_current_page: false,
				can_publish_visible_pages: false
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
				
				if (page.access_level === "p") {
					this.can_publish_current_page = true;
				}

				if (page.path) {
					BigTree.page_public_url = WWW_ROOT + page.path + "/";
				} else {
					BigTree.page_public_url = WWW_ROOT;
				}

				// Page Title
				BigTree.page_title = page.nav_title;

				// Breadcrumb
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

				BigTree.breadcrumb = breadcrumb.reverse();

				// Meta Bar
				let meta_bar = [];

				if (page.expires) {
					meta_bar.push({
						title: this.translate("Expires"),
						value: page.expires
					})
				}
				
				if (page.seo_score) {
					meta_bar.push({
						title: this.translate("SEO Score"),
						type: "visual",
						value: parseInt(page.seo_score)
					});
				}
				
				let visual_age = 100 * page.age / page.max_age;
				
				if (visual_age > 100) {
					visual_age = 100;
				}
				
				meta_bar.push({
					title: this.translate("Content Age"),
					type: "visual",
					value: 100 - visual_age,
					tooltip: {
						title: this.translate("Last Updated"),
						content: page.age === 1 ? this.translate("1 Day Ago") :
							this.translate(":count: Days Ago", { ":count:": page.age })
					}
				});
				
				BigTree.meta_bar = meta_bar;

				// Sub-nav
				let sub_nav = [{
					title: "Subpages",
					url: ADMIN_ROOT + "pages/" + page.id + "/",
					active: true
				}];

				if (page.access_level) {
					if (page.template && page.template !== "!") {
						sub_nav.push({
							title: "Content",
							url: ADMIN_ROOT + "pages/content/" + page.id + "/"
						});
					}

					sub_nav.push({
						title: "Page Properties",
						url: ADMIN_ROOT + "pages/properties/" + page.id + "/"
					});
				}

				if (page.access_level === "p") {
					sub_nav.push({
						title: "Revisions",
						url: ADMIN_ROOT + "pages/revisions/" + page.id + "/"
					});
				}

				if (BigTree.user_level > 0) {
					sub_nav.push({
						title: "User Access",
						url: ADMIN_ROOT + "pages/user-access/" + page.id + "/"
					});
				}

				BigTree.sub_nav = sub_nav;

				// Sub-nav actions
				let sub_nav_actions = [];

				if (page.access_level) {
					sub_nav_actions.push({
						"title": "Add Subpage",
						"icon": "note_add",
						"url": ADMIN_ROOT + "pages/add/" + page.id + "/"
					});
				}

				if (page.access_level === "p") {
					sub_nav_actions.push({
						"title": "Move Current Page",
						"icon": "local_shipping",
						"url": ADMIN_ROOT + "pages/move/" + page.id + "/"
					});
				}

				BigTree.sub_nav_actions = sub_nav_actions;

				// Tools
				let tools = [];

				if (BigTree.user_level > 1) {
					if (page.template && page.template !== "!") {
						tools.push({
							title: "Edit Current Template in Developer",
							url: ADMIN_ROOT + "developer/templates/edit/" + page.template + "/",
							icon: "view_quilt"
						});
					}

					tools.push({
						title: "View Audit Trail for Page",
						url: ADMIN_ROOT + "developer/audit-trail/search/?table=bigtree_pages&entry=" + page.id,
						icon: "timeline"
					});
				}

				BigTree.tools = tools;
			},
			async data () {
				let d = await BigTreeAPI.getStoredDataMatching("pages", "parent", this.page);
				console.log(d);

				return d;
			}
		},
		computed: {
			archived_pages: function() {
				if (!this.data) {
					return [];
				}

				let pages = [];

				for (let x = 0; x < this.data.length; x++) {
					let page = this.data[x];

					if (page.archived) {
						page.actions = this.get_actions(page);
						page.status_tooltip = this.get_status_tooltip(page);
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

			draggable: function() {
				return this.can_publish_current_page && this.can_publish_visible_pages;
			},

			hidden_pages: function() {
				if (!this.data) {
					return [];
				}

				let pages = [];

				for (let x = 0; x < this.data.length; x++) {
					let page = this.data[x];

					if (!page.archived && !page.in_nav) {
						page.actions = this.get_actions(page);
						page.status_tooltip = this.get_status_tooltip(page);
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

			visible_pages: function() {
				if (!this.data) {
					return [];
				}
				
				let pages = [];
				let can_publish = true;
				
				for (let x = 0; x < this.data.length; x++) {
					let page = this.data[x];
					
					if (!page.archived && page.in_nav) {
						if (page.access_level !== "p") {
							can_publish = false;
						}
						
						page.actions = this.get_actions(page);
						page.status_tooltip = this.get_status_tooltip(page);
						pages.push(page);
					}
				}
				
				this.can_publish_visible_pages = can_publish;

				pages.sort(function(a, b) {
					const a_position = parseInt(a.position);
					const b_position = parseInt(b.position);

					if (a_position === b_position) {
						return 0;
					}

					return (a_position > b_position) ? -1 : 1;
				});
				
				return pages;
			}
		},
		methods: {
			get_actions: function(page) {
				if (page.archived) {
					if (page.access_level === "p") {
						return [
							{ title: "Restore", route: "unarchive" },
							{ title: "Delete Permanently", route: "delete" }
						];
					} else {
						return [];
					}
				}
				
				if (!page.access_level) {
					return [];
				}
				
				if (page.access_level === "e") {
					return [
						{ title: "Edit", route: "edit" },
						{ title: "Duplicate", route: "duplicate" },
						{ title: "Add Subpage", route: "add" },
						{ title: "View Page", url: WWW_ROOT + page.path + "/" }
					];
				}
				
				return [
					{ title: "Edit", route: "edit" },
					{ title: "Archive", route: "archive" },
					{ title: "Duplicate", route: "duplicate" },
					{ title: "Revisions", route: "revisions" },
					{ title: "Move", route: "move" },
					{ title: "Add Subpage", route: "add" },
					{ title: "View Page", url: WWW_ROOT + page.path + "/" }
				];
			},
			
			get_status_tooltip: function(page) {
				return page.status.replace(/(^([a-zA-Z\p{M}]))|([ -][a-zA-Z\p{M}])/g, function(s) {
					return s.toUpperCase();
				});
			},
			
			navigate: function(data) {
				let location = window.location.href;
				let slug = "/" + data.id + "/";
				let length = slug.length;
				
				if (location.substr(-1, 1) !== "/") {
					location = location + "/";
				}
				
				let new_location = location.replace("/" + this.page + "/", slug);
				
				if (new_location.substr(-1 * length, length) !== slug) {
					new_location = location + data.id + "/";
				}
				
				window.history.pushState({
					content: window.history.state.content,
					state: window.history.state.state,
					page: data.id
				}, "", new_location);
				
				this.page = data.id;
			}
		},
		
		created: function() {
			if (this.$parent && typeof this.$parent.page !== "undefined") {
				this.page = this.$parent.page;
			}
		},
		
		mounted: function() {
			BigTreeEventBus.$on("breadcrumb-click", (id) => {
				this.navigate({ id: id });
			});
			
			BigTreeEventBus.$on("state-pop", (state) => {
				this.page = state.page;
			});

			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "pages") {
					this.$asyncComputed.data.update();
					this.$asyncComputed.current_page_data.update();
				}
			});
			
			BigTreeEventBus.$on("data-table-resorted", async (table) => {
				let data = table.mutable_data;
				let children = [];
				
				for (let x = 0; x < data.length; x++) {
					children.push(data[x].id);
				}
				
				await BigTreeAPI.call({
					endpoint: "pages/order",
					method: "POST",
					parameters: {
						"parent": this.page,
						"positioned_children": children
					}
				});
			});
		}
	});
</script>

<template>
		<alert v-if="data && !visible_pages.length && !hidden_pages.length && !archived_pages.length" type="notice" title="No Subpages Yet">
			There are currently no subpages.<br>
			• Create a subpage by clicking the &ldquo;Add Subpage&rdquo; button.<br>
			• Edit this page by clicking the &ldquo;Content&rdquo; tab.
		</alert>

		<div v-else>
			<toggle-block title="Visible in Navigation" :id="'pages-visible-' + page">
				<table-draggable v-if="draggable " v-on:row-click="navigate" clickable_rows="true" no_search="true"
								 :data="visible_pages" escaped_data="true" data_contains_actions="true"
								 actions_base_path="pages" :columns="[
					{ 'title': 'Title', 'key': 'nav_title' },
					{ 'title': 'Status', 'key': 'status', 'type': 'status', tooltip_key: 'status_tooltip' }
				]"></table-draggable>
				<table-simple v-else v-on:row-click="navigate" clickable_rows="true"
							  :data="visible_pages" escaped_data="true" data_contains_actions="true"
							  actions_base_path="pages" :columns="[
					{ 'title': 'Title', 'key': 'nav_title' },
					{ 'title': 'Status', 'key': 'status', 'type': 'status', tooltip_key: 'status_tooltip' }
				]"></table-simple>
			</toggle-block>
	
			<toggle-block title="Hidden from Navigation" :id="'pages-hidden-' + page">
				<table-simple :data="hidden_pages" v-on:row-click="navigate" clickable_rows="true"
							  escaped_data="true" data_contains_actions="true" actions_base_path="pages"
							  :columns="[
					{ 'title': 'Title', 'key': 'nav_title' },
					{ 'title': 'Status', 'key': 'status', 'type': 'status', tooltip_key: 'status_tooltip' }
				]"></table-simple>
			</toggle-block>
	
			<toggle-block title="Archived" :id="'pages-archived-' + page">
				<table-simple :data="archived_pages" escaped_data="true"
							  data_contains_actions="true" actions_base_path="pages"
							  :columns="[
					{ 'title': 'Title', 'key': 'nav_title' },
					{ 'title': 'Status', 'key': 'status', 'type': 'status', tooltip_key: 'status_tooltip' }
				]"></table-simple>
			</toggle-block>
		</div>
</template>