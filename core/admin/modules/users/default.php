<?php
	namespace BigTree;

	$users = $admin->getUsers();
	$user_data = array();
	foreach ($users as $user) {
		if ($user["level"] <= Auth::user()->Level) {
			$user["gravatar"] = Image::gravatar($user["email"],36);
			$user_data[] = $user;
		}
	}
?>
<div id="user_table"></div>
<script>
	BigTreeTable({
		container: "#user_table",
		columns: {
			name: { title: "<?=Text::translate("Name", true)?>", source: '<span class="gravatar"><img src="{gravatar}" alt="" /></span>{name}', size: 0.4, sort: "asc" },
			email: { title: "<?=Text::translate("Email", true)?>", size: 0.3 },
			company: { title: "<?=Text::translate("Company", true)?>", size: 0.3 }
		},
		actions: {
			"edit": "<?=ADMIN_ROOT?>users/edit/{id}/",
			"delete": function(id,state) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete User", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this user?", true)?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
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