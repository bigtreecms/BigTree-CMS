<?	
	include "_setup.php";
	
	if ($item["approved"]) {
		if ($access_level != "p") {
			echo 'BigTree.Growl("'.$module["name"].'","You don\'t have permission to perform this action.");';
		} else {
			echo 'BigTree.Growl("'.$module["name"].'","Item is now unapproved.");';
			if (is_numeric($id)) {
				sqlquery("UPDATE `$table` SET approved = '' WHERE id = '$id'");
			} else {
				BigTreeAutoModule::updatePendingItemField(substr($id,1),"approved","");
			}
		}
	} else {
		if ($access_level != "p") {
			echo 'BigTree.Growl("'.$module["name"].'","You don\'t have permission to perform this action.");';
		} else {
			echo 'BigTree.Growl("'.$module["name"].'","Item is now approved.");';
			if (is_numeric($id)) {
				sqlquery("UPDATE `$table` SET approved = 'on' WHERE id = '$id'");
			} else {
				BigTreeAutoModule::updatePendingItemField(substr($id,1),"approved","on");
			}
		}
	}
	
	include "_recache.php";
?>