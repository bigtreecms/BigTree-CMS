<?
	header("Content-type: text/javascript");
	
	// Grab View Data
	
	$view = BigTreeAutoModule::getView($_GET["view"]);
	$module = $admin->getModule(BigTreeAutoModule::getModuleForView($_GET["view"]));
	$data = BigTreeAutoModule::getPendingItem($view["table"],$_GET["id"]);
	$permission = $admin->getAccessLevel($module,$data,$view["table"]);
		
	$table = $view["table"];
	$id = $_GET["id"];
	
	if (!$permission || $permission == "n" || $permission == "e") {
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