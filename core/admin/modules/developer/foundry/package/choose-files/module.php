<?	
	$module = $admin->getModule(end($commands));
	gatherModuleInformation($module["id"]);
	
	$default_name = $module["name"];
?>
<h1><span class="package"></span>Create Package</h1>
<div class="form_container">
	<header><p>Please select all the files required for the Module &ldquo;<?=$module["name"]?>&rdquo;</p></header>
	<form method="post" action="<?=$admin_root?>developer/foundry/package/process/" class="module">
		<input type="hidden" name="module" value="<?=$module["id"]?>" />
		<section>