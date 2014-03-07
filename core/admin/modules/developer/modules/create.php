<?
	BigTree::globalizePOSTVars();
	
	if ($group_new) {
		$group = $admin->createModuleGroup($group_new,"on");
	} else {
		$group = $group_existing;
	}
	
	$id = $admin->createModule($name,$group,$class,$table,$gbp,$icon,$route);
	// Route was incorrect if we failed
	if (!$id) {
		$_POST["group_existing"] = $group;
		unset($_POST["group_new"]);
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$admin->growl("Developer","Invalid Route");
		BigTree::redirect(DEVELOPER_ROOT."modules/add/?error=route");
	}
	
	if (!$table) {
		$admin->growl("Developer","Created Module");
		BigTree::redirect(DEVELOPER_ROOT."modules/");
	}
?>
<div class="container">
	<section>
		<h3><?=$name?></h3>
		<p>If you plan on programming this module manually, you can leave now. Otherwise, click the continue button below to setup the module's landing view.</p>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>modules/views/add/?new_module=true&module=<?=$id?>&table=<?=urlencode($table)?>&title=<?=urlencode($name)?>" class="button blue">Continue</a>	
	</footer>
</div>
