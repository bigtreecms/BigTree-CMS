<?php
	namespace BigTree;

	$users = User::all("name ASC", true);
	$levels = [
		0 => "Normal",
		1 => "Administrator",
		2 => "Developer"
	];
	
	foreach ($users as &$user) {
		$user["gravatar"] = User::gravatar($user["email"], 36);
		$user["level"] = $levels[$user["level"]];
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
			name: { title: "<?=Text::translate("Name", true)?>", source: '<span class="gravatar"><img src="{gravatar}" alt="" /></span>{name}', size: 0.35, sort: "asc" },
			email: { title: "<?=Text::translate("Email", true)?>", size: 0.25 },
			company: { title: "<?=Text::translate("Company", true)?>", size: 0.25 },
			level: { title: "<?=Text::translate("User Level", true)?>", size: 0.15 }
		},
		actions: {
			settings: "<?=DEVELOPER_ROOT?>user-emulator/emulate/?id={id}<?php CSRF::drawGETToken(); ?>"
		},
		data: <?=JSON::encodeColumns($users, ["id", "gravatar", "name", "email", "company", "level"])?>,
		searchable: true,
		sortable: true,
		perPage: 10
	});
</script>