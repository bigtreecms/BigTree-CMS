<?php
	$users = $admin->getUsers();
	foreach ($users as &$user) {
		$user["gravatar"] = BigTree::gravatar($user["email"],36);
	}
?>
<section class="inset_block">
	<p>The User Emulator allows you to login as another user of the CMS without knowing their password.<br />Upon clicking on the tool icon you will be logged in as the selected user.</p>
</section>
<div id="user_table"></div>
<script>
	BigTreeTable({
		container: "#user_table",
		title: "Users",
		columns: {
			name: { title: "Name", source: '<span class="gravatar"><img src="{gravatar}" alt="" /></span>{name}', size: 0.4, sort: "asc" },
			email: { title: "Email", size: 0.3 },
			company: { title: "Company", size: 0.3 }
		},
		actions: {
			settings: "<?=DEVELOPER_ROOT?>user-emulator/emulate/{id}/"
		},
		data: <?=BigTree::jsonExtract($users,array("id","gravatar","name","email","company"))?>,
		searchable: true,
		sortable: true,
		perPage: 10
	});
</script>