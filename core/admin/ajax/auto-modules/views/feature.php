<?
	include "_setup.php";

	if ($item["featured"]) {
		if ($access_level != "p") {
			echo 'BigTree.Growl("'.$module["name"].'","You don\'t have permission to perform this action.");';
		} else {
			echo 'BigTree.Growl("'.$module["name"].'","Item is now unfeatured.");';
			if (is_numeric($id)) {
				sqlquery("UPDATE `$table` SET featured = '' WHERE id = '$id'");
			} else {
				BigTreeAutoModule::updatePendingItemField(substr($id,1),"featured","");
			}
		}
	} else {
		if ($access_level != "p") {
			echo 'BigTree.Growl("'.$module["name"].'","You don\'t have permission to perform this action.");';
		} else {
			echo 'BigTree.Growl("'.$module["name"].'","Item is now featured.");';
			if (is_numeric($id)) {
				sqlquery("UPDATE `$table` SET featured = 'on' WHERE id = '$id'");
			} else {
				BigTreeAutoModule::updatePendingItemField(substr($id,1),"featured","on");
			}
		}
	}
	
	include "_recache.php";
?>