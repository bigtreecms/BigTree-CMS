<?
	BigTree::globalizePOSTVars();
	
	if ($group_new) {
		$group = $admin->createModuleGroup($group_new,"on");
	} else {
		$group = $group_existing;
	}
	
	$id = $admin->createModule($name,$group,$class,$table,$gbp,$icon);
	
	if (!$table) {
		$admin->growl("Developer","Created Module");
		BigTree::redirect($developer_root."modules/");
	}
?>
<div class="container">
	<section>
		<h3 class="action_title"><?=$name?></h3>
		<p>If you plan on programming this module manually, you can leave now. Otherwise, click the continue button below to setup the module's landing page.</p>
	</section>
	<footer>
		<a href="<?=$developer_root?>modules/views/add/?module=<?=$id?>&table=<?=urlencode($table)?>&title=<?=urlencode($name)?>" class="button blue">Continue</a>	
	</footer>
</div>
