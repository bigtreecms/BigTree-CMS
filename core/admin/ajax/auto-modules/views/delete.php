<?
	include "_setup.php";
	
	if ($perm != "p") {
		echo 'BigTree.growl("'.$module["name"].'","You don\'t have permission to delete this item.");';
	} else {
		echo 'BigTree.growl("'.$module["name"].'","Deleted item."); $("#row_'.$_GET["id"].'").remove();';
		
		if (substr($id,0,1) == "p") {
			BigTreeAutoModule::deletePendingItem($table,substr($id,1));
		} else {
			BigTreeAutoModule::deleteItem($table,$id);
		}
	}
?>