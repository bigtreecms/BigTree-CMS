<?
	$group = $admin->getModuleGroup(end($bigtree["commands"]));
	$modules = $admin->getModulesByGroup($group);
	foreach ($modules as $m) {
		gatherModuleInformation($m["id"]);
	}

	if (file_exists(SERVER_ROOT."custom/inc/required/".$cms->urlify($group["name"]).".php")) {
		$required_files[] = "custom/inc/required/".$cms->urlify($group["name"]).".php";
	}
	
	$default_name = $group["name"];
?>
<div class="container">
	<header><p>Please select all the files required for the Module Group &ldquo;<?=$group["name"]?>&rdquo;</p></header>
	<form method="post" action="<?=ADMIN_ROOT?>developer/foundry/package/process/" class="module">
		<input type="hidden" name="group" value="<?=$group["id"]?>" />
		<section>