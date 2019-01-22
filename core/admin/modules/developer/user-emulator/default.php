<?php
	namespace BigTree;

	$users = User::all("name ASC", true);
	
	foreach ($users as &$user) {
		$user["gravatar"] = User::gravatar($user["email"], 36);
	}
?>
<section class="inset_block">
	<p><?=Text::translate("The User Emulator allows you to login as another user of the CMS without knowing their password.<br />Upon clicking on the tool icon you will be logged in as the selected user.")?></p>
</section>
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
			settings: "<?=DEVELOPER_ROOT?>user-emulator/emulate/?id={id}<?php CSRF::drawGETToken(); ?>"
		},
		data: <?=JSON::encodeColumns($users, array("id", "gravatar", "name", "email", "company"))?>,
		searchable: true,
		sortable: true,
		perPage: 10
	});
</script>