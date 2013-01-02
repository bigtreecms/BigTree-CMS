<?	
	$module = $admin->getModule(end($bigtree["commands"]));
	gatherModuleInformation($module["id"]);
	
	$default_name = $module["name"];
?>
<div class="container">
	<header><p>Please select all the files required for the Module &ldquo;<?=$module["name"]?>&rdquo;</p></header>
	<form method="post" action="<?=ADMIN_ROOT?>developer/foundry/package/process/" class="module">
		<input type="hidden" name="module" value="<?=$module["id"]?>" />
		<section>