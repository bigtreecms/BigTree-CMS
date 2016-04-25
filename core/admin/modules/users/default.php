<?php
	namespace BigTree;

	$users = $admin->getUsers();
	$user_data = array();
	foreach ($users as $user) {
		if ($user["level"] <= $admin->Level) {
			$user["gravatar"] = \BigTree::gravatar($user["email"],36);
			$user_data[] = $user;
		}
	}
?>
<div id="user_table"></div>
<script>
	BigTreeTable({
		container: "#user_table",
		columns: {
			name: { title: "Name", source: '<span class="gravatar"><img src="{gravatar}" alt="" /></span>{name}', size: 0.4, sort: "asc" },
			email: { title: "Email", size: 0.3 },
			company: { title: "Company", size: 0.3 }
		},
		actions: {
			edit: "<?=ADMIN_ROOT?>users/edit/{id}/",
			delete: function(id,state) {
				BigTreeDialog({
					title: "Delete User",
					content: '<p class="confirm">Are you sure you want to delete this user?</p>',
					icon: "delete",
					alternateSaveText: "OK",
					callback: function() {
						document.location.href = "<?=ADMIN_ROOT?>users/delete/" + id + "/";
					}
				});
			}
		},
		data: <?=JSON::encodeColumns($user_data,array("id","gravatar","name","email","company"))?>,
		searchable: true,
		sortable: true,
		perPage: 10
	});
</script>