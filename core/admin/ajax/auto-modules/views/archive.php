<?
	include "_setup.php";
	
	if ($item["archived"]) {
		if ($access_level != "p") {
			echo 'BigTree.Growl("'.$module["name"].'","You don\'t have permission to perform this action.");';
		} else {
			echo 'BigTree.Growl("'.$module["name"].'","Item is now unarchived.");';
			if (is_numeric($id)) {
				sqlquery("UPDATE `$table` SET archived = '' WHERE id = '$id'");
			} else {
				BigTreeAutoModule::updatePendingItemField(substr($id,1),"archived","");
			}
		}
	} else {
		if ($access_level != "p") {
			echo 'BigTree.Growl("'.$module["name"].'","You don\'t have permission to perform this action.");';
		} else {
			echo 'BigTree.Growl("'.$module["name"].'","Item is now archived.");';
			if (is_numeric($id)) {
				sqlquery("UPDATE `$table` SET archived = 'on' WHERE id = '$id'");
			} else {
				BigTreeAutoModule::updatePendingItemField(substr($id,1),"archived","on");
			}
		}
	}
	
	include "_recache.php";
?>