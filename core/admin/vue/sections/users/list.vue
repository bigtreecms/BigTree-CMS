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
		},

		mounted: function() {
			BigTreeEventBus.$on("api-data-changed", (store) => {
				if (store === "users") {
					this.$asyncComputed.data.update();
				}
			});
		}
	});
</script>

<template>
	<div class="component layout_expanded">
		<data-table actions_base_path="users" searchable="true" per_page="10" :columns="[
			{ 'title': this.translate('Name'), 'key': 'name', 'sort': true, 'sort_default': 'ASC', 'width': '35%' },
			{ 'title': this.translate('Email'), 'key': 'email', 'sort': true, 'width': '25%' },
			{ 'title': this.translate('Company'), 'key': 'company', 'sort': true, 'width': '25%' },
			{ 'title': this.translate('User Level'), 'key': 'level', 'sort': true, 'width': '15%' }
		]" :actions="[
			{ 'title': this.translate('Edit User'), 'route': 'edit' },
			{ 'title': this.translate('Delete User'), 'route': 'delete' }
		]" :data="data" sortable="true" escaped_data="true"></data-table>
	</div>
</template>