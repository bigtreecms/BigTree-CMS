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
		die("Not going to happen.");
	}

	// This is an update to an existing entry.
	if ($change["item_id"]) {
		BigTreeAutoModule::updateItem($change["table"],$change["item_id"],$change["changes"],$changes["mtm_changes"],$changes["tags_changes"]);
	// It's a new entry, let's publish it.
	} else {
		if ($change["table"] == "bigtree_pages") {
			$page = $admin->createPage($change["changes"]);
			$admin->deletePendingChange($change["id"]);
		} else {
			BigTreeAutoModule::publishPendingItem($change["table"],$change["id"],$change["changes"],$changes["mtm_changes"],$changes["tags_changes"]);
		}
	}
?>