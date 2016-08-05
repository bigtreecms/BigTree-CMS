<?php
	namespace BigTree;
	
	if ($_POST["group_new"]) {
		$group = ModuleGroup::create($_POST["group_new"]);
		$group_id = $group->ID;
	} else {
		$group_id = intval($_POST["group_existing"]);
	}
	
	$module = Module::create($_POST["name"], $group_id, $_POST["class"], $_POST["table"], $_POST["gbp"], $_POST["icon"],
							 $_POST["route"], $_POST["developer_only"]);
	
	// Route was incorrect if we failed
	if ($module === false) {
		// We already created the group
		$_POST["group_existing"] = $group_id;
		unset($_POST["group_new"]);
		
		// Save user entry and redirect back
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		Utils::growl("Developer","Invalid Route");
		Router::redirect(DEVELOPER_ROOT."modules/add/?error=route");
	}
	
	// If this thing doesn't have a table it's probably being manually created - can't create a view/form for it
	if (!$_POST["table"]) {
		Utils::growl("Developer","Created Module");
		Router::redirect(DEVELOPER_ROOT."modules/");
	}
?>
<div class="container">
	<section>
		<h3><?=$module->Name?></h3>
		<p><?=Text::translate("If you plan on programming this module manually, you can leave now. Otherwise, click the continue button below to setup the module's landing view.")?></p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>modules/views/add/?new_module=true&module=<?=$module->ID?>&table=<?=urlencode($module->Table)?>&title=<?=urlencode($module->Name)?>" class="button blue"><?=Text::translate("Continue")?></a>
	</footer>
</div>