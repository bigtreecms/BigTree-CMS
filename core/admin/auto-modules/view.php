<?
	$view = BigTreeAutoModule::getView($bigtree["module_action"]["view"]);
	
	// Setup the preview action if we have a preview URL and field.
	if ($view["preview_url"]) {
		$view["actions"]["preview"] = "on";
	}

	if ($view["description"] && !$_COOKIE["bigtree_admin"]["ignore_view_description"][$view["id"]]) {
?>
<section class="inset_block">
	<span class="hide" data-id="<?=$view["id"]?>">x</span>
	<p><?=$view["description"]?></p>
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
	
	include BigTree::path("admin/auto-modules/views/".$view["type"].".php");
?>