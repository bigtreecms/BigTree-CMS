<script>
	Vue.component("page-users-listing", {
		asyncComputed: {
			async data () {
				let users = await BigTreeAPI.getStoredData("users", "name");

				return users;
			}
		}
	});
	
	Vue.mixin({
		methods: {
			translate: function(string) {
				return "poop";
			}
		}
	});
</script>

<template>
	<div class="component layout_expanded">
		<data-table actions_base_path="users" searchable="true" per_page="10" translatable="true" :columns="[
			{ 'title': 'Name', 'key': 'name' },
			{ 'title': 'Email', 'key': 'email' },
			{ 'title': 'Company', 'key': 'company' },
			{ 'title': 'User Level', 'key': 'level' }
		]" :actions="[
			{ 'title': 'Edit User', 'route': 'edit' },
			{ 'title': 'Delete User', 'route': 'delete' }
		]" :data="data" escaped_data="true"></data-table>
	</div>
</template>