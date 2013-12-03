<?
	$change = $admin->getPendingChange($_POST["id"]);

	// See if we have permission.
	$item_id = $change["item_id"] ? $change["item_id"] : "p".$change["id"];
	
	if ($change["module"]) {
		// It's a module. Check permissions on this.
		$data = BigTreeAutoModule::getPendingItem($change["table"],$item_id);
		$permission_level = $admin->getAccessLevel($admin->getModule($change["module"]),$data["item"],$change["table"]);
	} else {
		if ($change["item_id"]) {
			$permission_level = $admin->getPageAccessLevel($page);
		} else {
			$f = $admin->getPendingChange($change["id"]);
			$permission_level = $admin->getPageAccessLevel($f["changes"]["parent"]);
		}
	}
	
	// If they're not a publisher, they have no business here.
	if ($permission_level != "p") {
		die();
	}
	
	$admin->deletePendingChange($change["id"]);
	
	if (!is_numeric($item_id)) {
		BigTreeAutoModule::uncacheItem($item_id,$change["table"]);
	} else {
		BigTreeAutoModule::recacheItem($item_id,$change["table"]);
	}
?>