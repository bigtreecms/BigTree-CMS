<?php
	namespace BigTree;

	$change = new PendingChange($_POST["id"]);

	// See if we have permission.
	$item_id = $change->ItemID ?: "p".$change->ID;
	
	// It's a module. Check permissions on this.
	if ($change->Module) {
		$form = new ModuleForm(array("table" => $change->Table));
		$module = new Module($change->Module);
		$data = $form->getPendingEntry($item_id);
		$access_level = $module->getUserAccessLevelForEntry($data["item"], $form->Table);
	// It's a page
	} else {
		// Published page
		if (!is_null($change->ItemID)) {
			$page = new Page($change->ItemID);
			$access_level = $page->UserAccessLevel;
		// Pending page we'll check parent's permissions
		} else {
			$form = new ModuleForm(array("table" => "bigtree_pages"));
			$data = $form->getPendingEntry($change->ID);

			$page = new Page($data["changes"]["parent"]);
			$access_level = $page->UserAccessLevel;
		}
	}
	
	// If they're not a publisher, they have no business here.
	if ($access_level != "p") {
		die();
	}

	$change->delete();

	if (!is_numeric($item_id)) {
		ModuleView::uncacheForAll($item_id, $change->Table);
	} else {
		ModuleView::cacheForAll($item_id, $change->Table);
	}