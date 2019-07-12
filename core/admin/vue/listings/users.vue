<script>
	Vue.component("page-users-listing", {
		asyncComputed: {
			async data () {
				const user_levels = [
					this.translate("Normal"),
					this.translate("Administrator"),
					this.translate("Developer")
				];

				let users = await BigTreeAPI.getStoredData("users", "name");

				for (let x = 0; x < users.length; x++) {
					users[x].level = user_levels[users[x].level];
				}

				return users;
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