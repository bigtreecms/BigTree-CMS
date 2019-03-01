<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 * @global ModuleInterface $interface
	 */

	$view = new ModuleView($interface->Array);
	$view->calculateFieldWidths();
	$bigtree["view"] = $view->Array;

	if ($view->Description && !$_COOKIE["bigtree_admin"]["ignore_view_description"][$view->ID]) {
?>
<section class="inset_block">
	<span class="hide" data-id="<?=$view->ID?>">x</span>
	<p><?=$view->Description?></p>
</section>
<?php
	}
	
	// Extension view
	if (strpos($view->Type,"*") !== false) {
		list($extension,$view_type) = explode("*",$view->Type);
		include SERVER_ROOT."extensions/$extension/plugins/view-types/$view_type/draw.php";
	} else {
		include Router::getIncludePath("admin/auto-modules/views/".$view->Type.".php");
	}
?>