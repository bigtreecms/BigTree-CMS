<?
	$view = BigTreeAutoModule::getView($bigtree["module_action"]["view"]);
	
	// Setup the preview action if we have a preview URL and field.
	if ($view["preview_url"]) {
		$view["actions"]["preview"] = "on";
	}

	if ($view["description"]) {
		echo "<p>".$view["description"]."</p>";
	}

	$maction = $action;

	$action_names = array(
		"approve" => "Approve/Deny",
		"edit" => "Edit",
		"delete" => "Delete",
		"archive" => "Archive/Unarchive",
		"featured" => "Featured"
	);
	
	include BigTree::path("admin/auto-modules/views/".$view["type"].".php");

	$action = $maction;
?>
