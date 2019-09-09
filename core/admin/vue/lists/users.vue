<script>
	Vue.component("UsersList", {
		asyncComputed: {
			async data () {
				const user_levels = [
					this.translate("Normal"),
					this.translate("Administrator"),
					this.translate("Developer")
				];

				let users = await BigTreeAPI.getStoredData("users");

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
		<DataTable actions_base_path="users" searchable="true" per_page="10" translatable="true" :columns="[
			{ 'title': 'Name', 'key': 'name', 'sort': true, 'sort_default': 'ASC', 'width': '35%' },
			{ 'title': 'Email', 'key': 'email', 'sort': true, 'width': '25%' },
			{ 'title': 'Company', 'key': 'company', 'sort': true, 'width': '25%' },
			{ 'title': 'User Level', 'key': 'level', 'sort': true, 'width': '15%' }
		]" :actions="[
			{ 'title': 'Edit User', 'route': 'edit' },
			{ 'title': 'Delete User', 'route': 'delete' }
		]" :data="data" sortable="true" escaped_data="true"></DataTable>
	</div>
</template>