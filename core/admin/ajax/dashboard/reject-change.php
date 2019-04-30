<?php
	namespace BigTree;
	
	CSRF::verify();

	$change = new PendingChange($_POST["id"]);

	// See if we have permission.
	$item_id = $change->ItemID ?: "p".$change->ID;
	
	// It's a module. Check permissions on this.
	if ($change->Module) {
		$form = new ModuleForm(["table" => $change->Table]);
		$module = new Module($change->Module);
		$data = $form->getPendingEntry($item_id);
		$access_level = Auth::user()->getAccessLevel($module, $data["item"], $form->Table);
	// It's a page
	} else {
		// Published page
		if (!is_null($change->ItemID)) {
			$page = new Page($change->ItemID);
			$access_level = $page->UserAccessLevel;
		// Pending page we'll check parent's permissions
		} else {
			$form = new ModuleForm(["table" => "bigtree_pages"]);
			$data = $form->getPendingEntry($change->ID);

			$page = new Page($data["changes"]["parent"]);
			$access_level = $page->UserAccessLevel;
		}
	}
	
	// If they're not a publisher, they have no business here.
	if ($access_level != "p") {
		die();
	}
	
	Resource::deallocate($change->Table, "p".$change->ID);
	$change->delete();

	if (!is_numeric($item_id)) {
		ModuleView::uncacheForAll($change->Table, $item_id);
	} else {
		ModuleView::cacheForAll($change->Table, $item_id);
	}
	