<?
	$group = $admin->getModuleGroup(end($commands));
	$modules = $admin->getModulesByGroup($group);
	foreach ($modules as $m) {
		gatherModuleInformation($m["id"]);
	}

	if (file_exists($server_root."custom/inc/required/".$cms->urlify($group["name"]).".php")) {
		$required_files[] = "custom/inc/required/".$cms->urlify($group["name"]).".php";
	}
	
	$default_name = $group["name"];
?>
<h1><span class="package"></span>Create Package</h1>
<div class="form_container">
	<header><p>Please select all the files required for the Module Group &ldquo;<?=$group["name"]?>&rdquo;</p></header>
	<form method="post" action="<?=$admin_root?>developer/foundry/package/process/" class="module">
		<input type="hidden" name="group" value="<?=$group["id"]?>" />
		<section>