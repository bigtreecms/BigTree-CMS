<?
	$bigtree["view"] = $view = BigTreeAutoModule::getView($bigtree["module_action"]["view"]);

	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$bigtree["view"]["actions"]["preview"] = "on";
	}

	// Provide developers a nice handy link for edit/return of this view
	if ($admin->Level > 1) {
		$bigtree["subnav_extras"][] = array("link" => ADMIN_ROOT."developer/modules/views/edit/".$bigtree["view"]["id"]."/?return=front","icon" => "setup","title" => "Edit in Developer");
	}
	
	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$bigtree["view"]["actions"]["preview"] = "on";
	}

	if ($bigtree["view"]["description"] && !$_COOKIE["bigtree_admin"]["ignore_view_description"][$bigtree["view"]["id"]]) {
?>
<section class="inset_block">
	<span class="hide" data-id="<?=$bigtree["view"]["id"]?>">x</span>
	<p><?=$bigtree["view"]["description"]?></p>
</section>
<?
	}
	
	include BigTree::path("admin/auto-modules/views/".$bigtree["view"]["type"].".php");
?>