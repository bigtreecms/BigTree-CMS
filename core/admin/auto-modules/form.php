<?
	include BigTree::path("admin/auto-modules/_setup.php");
	
	$form = BigTreeAutoModule::getForm($action["form"]);
	
	if ($form["css"]) {
		$css = explode(",",$form["css"]);
	}
	if ($form["javascript"]) {
		$js = explode(",",$form["javascript"]);
	}
	
	$action = end($path);
	
	if ($action == "process" || $action == "preview") {
		include BigTree::path("admin/auto-modules/forms/process.php");
	} elseif ($action == "process-crops") {
		include BigTree::path("admin/auto-modules/forms/process-crops.php");
	} elseif (isset($_GET["force"])) {
		include BigTree::path("admin/auto-modules/forms/unlock.php");
	} elseif ($edit_id) {
		include BigTree::path("admin/auto-modules/forms/edit.php");
	} else {
		include BigTree::path("admin/auto-modules/forms/add.php");
	}
?>