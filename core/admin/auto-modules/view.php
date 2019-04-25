<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 * @global ModuleInterface $interface
	 */

	$view = $interface->Module->Views[$interface->ID];
	$view->calculateFieldWidths();
	$bigtree["view"] = $view->Array;

	if ($view->Description) {
?>
<section class="inset_block js-view-description"<?php if ($_COOKIE["bigtree_admin"]["ignore_view_description"][$view->ID]) { ?> style="display: none;"<?php } ?> data-id="<?=$view->ID?>">
	<span class="hide js-view-description-hide">x</span>
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