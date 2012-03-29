<?	
	header("Content-type: text/javascript");

	$id = mysql_real_escape_string($_GET["id"]);
	
	// Grab View Data
	$view = BigTreeAutoModule::getView($_GET["view"]);
	$table = $view["table"];
	$module = $admin->getModule(BigTreeAutoModule::getModuleForView($_GET["view"]));
	$perm = $admin->getAccessLevel($module,$item,$table);
	$item = sqlfetch(sqlquery("SELECT * FROM `$table` WHERE id = '$id'"));
	
	if ($item["approved"]) {
		if ($perm != "p") {
			echo 'BigTree.growl("'.$module["name"].'","You don\'t have permission to perform this action.");';
		} else {
			echo 'BigTree.growl("'.$module["name"].'","Item is now unapproved.");';
			sqlquery("UPDATE `$table` SET approved = '' WHERE id = '$id'");
		}
	} else {
		if ($perm != "p") {
			echo 'BigTree.growl("'.$module["name"].'","You don\'t have permission to perform this action.");';
		} else {
			echo 'BigTree.growl("'.$module["name"].'","Item is now approved.");';
			sqlquery("UPDATE `$table` SET approved = 'on' WHERE id = '$id'");
		}
	}
	
	BigTreeAutoModule::recacheItem($id,$table);
?>