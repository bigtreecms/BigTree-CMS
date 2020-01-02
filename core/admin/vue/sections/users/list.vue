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
		
		data: function() {
			return {
				actions: [
					{
						title: this.translate("Edit User"),
						route: "edit"
					},
					{
						title: this.translate("Delete User"),
						method: this.delete,
						confirm: this.translate("Are you sure you want to delete this user?")
					}
				],
				columns: [
					{
						title: this.translate("Name"),
						key: "name",
						sort: true,
						sort_default: "ASC"
					},
					{
						title: this.translate("Email"),
						key: "email",
						sort: true
					},
					{
						title: this.translate("Company"),
						key: "company",
						sort: true
					},
					{
						title: this.translate("User Level"),
						key: "level",
						sort: true
					}
				]
			}
		},
		
		methods: {
			delete: async function(id) {
				let response = await BigTreeAPI.call({
					endpoint: "users/delete",
					method: "POST",
					parameters: {
						id: id
					}
				});

				if (response.error) {
					BigTree.notification = {
						type: "error",
						context: this.translate("Deletion Failed"),
						message: this.translate(response.message)
					};
				} else {
					BigTree.notification = {
						type: "success",
						context: this.translate("Users"),
						message: this.translate("Deleted User")
					};

					this.$asyncComputed.data.update();
				}
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
		<table-sortable actions_base_path="users" per_page="10" :columns="columns" :actions="actions"
						:data="data" escaped_data="true"></table-sortable>
	</div>
</template>