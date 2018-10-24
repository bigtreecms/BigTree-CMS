<?php
	$admin->verifyCSRFToken();
	$change = $admin->getPendingChange($_POST["id"]);

	// See if we have permission.
	$item_id = $change["item_id"] ? $change["item_id"] : "p".$change["id"];
	
	if ($change["module"]) {
		// It's a module. Check permissions on this.
		$data = BigTreeAutoModule::getPendingItem($change["table"],$item_id);
		$permission_level = $admin->getAccessLevel($admin->getModule($change["module"]),$data["item"],$change["table"]);
	} else {
		if ($change["item_id"]) {
			$permission_level = $admin->getPageAccessLevel($item_id);
		} else {
			$f = $admin->getPendingChange($change["id"]);
			$permission_level = $admin->getPageAccessLevel($f["changes"]["parent"]);
		}
	}
	
	// If they're not a publisher, they have no business here.
	if ($permission_level != "p") {
		die("Permission denied.");
	}

	$change["changes"] = BigTreeAutoModule::sanitizeData($change["table"],$change["changes"]);

	// This is an update to an existing entry.
	if (!is_null($change["item_id"])) {
		if ($change["table"] == "bigtree_pages") {
			$page_data = $cms->getPendingPage($change["item_id"], true, true, true);
			$page_data["_open_graph_"] = $page_data["open_graph"];
			$page_data["_tags"] = [];

			foreach ($page_data["tags"] as $tag) {
				$page_data["_tags"][] = $tag["id"];
			}

			$admin->updatePage($change["item_id"], $page_data);
			$admin->updateResourceAllocation("bigtree_pages", $change["item_id"], $change["id"]);
		} else {
			BigTreeAutoModule::updateItem($change["table"],$change["item_id"],$change["changes"],$change["mtm_changes"],$change["tags_changes"],$change["open_graph_changes"]);
			$admin->updateResourceAllocation($change["table"], $change["item_id"], $change["id"]);

			if ($change["publish_hook"]) {
				call_user_func($change["publish_hook"],$change["table"],$change["item_id"],$change["changes"],$change["mtm_changes"],$change["tags_changes"],$change["open_graph_changes"]);
			}
		}
	// It's a new entry, let's publish it.
	} else {
		if ($change["table"] == "bigtree_pages") {
			$change["changes"]["_tags"] = $change["tags_changes"];
			$change["changes"]["_open_graph_"] = $change["open_graph_changes"];
			$page = $admin->createPage($change["changes"], $change["id"]);
			$admin->updateResourceAllocation($change["table"], $page, $change["id"]);
		} else {
			$id = BigTreeAutoModule::publishPendingItem($change["table"],$change["id"],$change["changes"],$change["mtm_changes"],$change["tags_changes"],$change["open_graph_changes"]);
			$admin->updateResourceAllocation($change["table"], $id, $change["id"]);

			if ($change["publish_hook"]) {
				call_user_func($change["publish_hook"],$change["table"],$id,$change["changes"],$change["mtm_changes"],$change["tags_changes"],$change["open_graph_changes"]);
			}
		}
	}
