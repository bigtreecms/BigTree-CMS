<?php
	namespace BigTree;
	
	\BigTree::globalizePOSTVars();
	
	if ($group_new) {
		$group = $admin->createModuleGroup($group_new,"on");
	} else {
		$group = $group_existing;
	}
	
	$id = $admin->createModule($name,$group,$class,$table,$gbp,$icon,$route,$developer_only);
	// Route was incorrect if we failed
	if (!$id) {
		$_POST["group_existing"] = $group;
		unset($_POST["group_new"]);
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$admin->growl("Developer","Invalid Route");
		Router::redirect(DEVELOPER_ROOT."modules/add/?error=route");
	}
	
	if (!$table) {
		$admin->growl("Developer","Created Module");
		Router::redirect(DEVELOPER_ROOT."modules/");
	}
?>
<div class="container">
	<section>
		<h3><?=htmlspecialchars($name)?></h3>
		<p><?=Text::translate("If you plan on programming this module manually, you can leave now. Otherwise, click the continue button below to setup the module's landing view.")?></p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>modules/views/add/?new_module=true&module=<?=$id?>&table=<?=htmlspecialchars(urlencode($table))?>&title=<?=htmlspecialchars(urlencode($name))?>" class="button blue"><?=Text::translate("Continue")?></a>	
	</footer>
</div>