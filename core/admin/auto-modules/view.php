<?
	$view = BigTreeAutoModule::getView($bigtree["module_action"]["view"]);
	
	// Setup the preview action if we have a preview URL and field.
	if ($view["preview_url"]) {
		$view["actions"]["preview"] = "on";
	}

	if ($view["description"]) {
?>
<div class="container">
	<section>
		<p><?=$view["description"]?></p>
	</section>
</div>
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