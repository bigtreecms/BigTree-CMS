<?php
	$bigtree["view"] = $view = BigTreeAutoModule::getView($bigtree["module_action"]["view"]);

	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$bigtree["view"]["actions"]["preview"] = "on";
	}
	
	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$bigtree["view"]["actions"]["preview"] = "on";
	}

	if ($bigtree["view"]["description"]) {
?>
<section class="inset_block js-view-description"<?php if ($_COOKIE["bigtree_admin"]["ignore_view_description"][$bigtree["view"]["id"]]) { ?> style="display: none;"<?php } ?> data-id="<?=$bigtree["view"]["id"]?>">
	<span class="hide js-view-description-hide">x</span>
	<p><?=$bigtree["view"]["description"]?></p>
</section>
<?php
	}
	
	include BigTree::path("admin/auto-modules/views/".$bigtree["view"]["type"].".php");
?>