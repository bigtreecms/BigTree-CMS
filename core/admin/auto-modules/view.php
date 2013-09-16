<?
	$bigtree["view"] = $view = BigTreeAutoModule::getView($bigtree["module_action"]["view"]);

	// Provide developers a nice handy link for edit/return of this form
	$bigtree["developer_nav_link"] = ADMIN_ROOT."developer/modules/views/edit/".$bigtree["view"]["id"]."/?return=front";
	
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

	$action_names = array(
		"approve" => "Approve/Deny",
		"edit" => "Edit",
		"delete" => "Delete",
		"archive" => "Archive/Unarchive",
		"featured" => "Featured"
	);
	
	include BigTree::path("admin/auto-modules/views/".$bigtree["view"]["type"].".php");
?>