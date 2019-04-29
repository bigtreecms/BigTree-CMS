<?php
	namespace BigTree;

	$users = User::all("name ASC", true);
	$user_data = [];
	$levels = [
		0 => Text::translate("Normal"),
		1 => Text::translate("Administrator"),
		2 => Text::translate("Developer")
	];
	
	foreach ($users as $user) {
		if ($user["level"] <= Auth::user()->Level) {
			$user["gravatar"] = User::gravatar($user["email"], 36);
			$user["level"] = $levels[$user["level"]];
			$user_data[] = $user;
		}
	}
?>
<div id="user_table"></div>
<script>
	BigTreeTable({
		container: "#user_table",
		columns: {
			name: { title: "<?=Text::translate("Name", true)?>", source: '<span class="gravatar"><img src="{gravatar}" alt="" /></span>{name}', size: 0.35, sort: "asc" },
			email: { title: "<?=Text::translate("Email", true)?>", size: 0.25 },
			company: { title: "<?=Text::translate("Company", true)?>", size: 0.25 },
			level: { title: "<?=Text::translate("User Level", true)?>", size: 0.15 }
		},
		actions: {
			"edit": "<?=ADMIN_ROOT?>users/edit/{id}/",
			"delete": function(id) {
				BigTreeDialog({
					title: "<?=Text::translate("Delete User", true)?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this user?", true)?></p>',
					icon: "delete",
					alternateSaveText: "<?=Text::translate("OK", true)?>",
					callback: function() {
						document.location.href = "<?=ADMIN_ROOT?>users/delete/?id=" + id + "<?php CSRF::drawGETToken(); ?>";
					}
				});
			}
		},
		data: <?=JSON::encodeColumns($user_data, ["id", "gravatar", "name", "email", "company", "level"])?>,
		searchable: true,
		sortable: true,
		perPage: 10
	});
</script>