<?
	include "_setup.php";
	
	if ($access_level != "p") {
		echo 'BigTree.Growl("'.$module["name"].'","You don\'t have permission to delete this item.");';
	} else {
		echo 'BigTree.Growl("'.$module["name"].'","Deleted item."); $("#row_'.$_GET["id"].'").remove();';
		
		if (substr($id,0,1) == "p") {
			BigTreeAutoModule::deletePendingItem($table,substr($id,1));
		} else {
			BigTreeAutoModule::deleteItem($table,$id);
		}
	}
?>