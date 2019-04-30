<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$change = new PendingChange($_POST["id"]);

	// See if we have permission.
	$item_id = !is_null($change->ItemID) ? $change->ItemID : "p".$change->ID;
	
	// It's a module. Check permissions on this.
	if ($change->Module) {
		$module = new Module($change->Module);
		$form = new ModuleForm(["table" => $change->Table]);

		$data = $form->getPendingEntry($item_id);
		$access_level = Auth::user()->getAccessLevel($module, $data["item"], $change->Table);
	// Page
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
		die("Permission denied.");
	}

	$change->Changes = SQL::prepareData($change->Table, $change->Changes);

	// This is an update to an existing entry.
	if (!is_null($change->ItemID)) {
		if ($change->Table == "bigtree_pages") {
			$page = Page::getPageDraft($change->ItemID);
			$page->save();
		} else {
			$form->updateEntry($change->ItemID, $change->Changes, $change->ManyToManyChanges, $change->TagsChanges);
			
			if ($change["publish_hook"]) {
				call_user_func($change->PublishHook, $change->Table, $change->ItemID, $change->Changes, $change->ManyToManyChanges, $change->TagsChanges);
			}
		}
		
		Resource::updatePendingAllocation($change->ID, $change->Table, $change->ItemID);
	// It's a new entry, let's publish it.
	} else {
		if ($change->Table == "bigtree_pages") {
			$page = Page::create(
				$change->Changes["trunk"],
				$change->Changes["parent"],
				$change->Changes["in_nav"],
				$change->Changes["nav_title"],
				$change->Changes["title"],
				$change->Changes["route"],
				$change->Changes["meta_description"],
				$change->Changes["seo_invisible"],
				$change->Changes["template"],
				$change->Changes["external"],
				$change->Changes["new_window"],
				$change->Changes["resources"],
				$change->Changes["publish_at"],
				$change->Changes["expire_at"],
				$change->Changes["max_age"],
				$change->TagsChanges
			);
			
			Resource::updatePendingAllocation($change->ID, "bigtree_pages", $page->ID);
		} else {
			$id = $form->createEntry($change->Changes, $change->ManyToManyChanges, $change->TagsChanges, $change->ID);

			if ($change->PublishHook) {
				call_user_func($change->PublishHook, $change->Table, $id, $change->Changes, $change->ManyToManyChanges, $change->TagsChanges);
			}
			
			Resource::updatePendingAllocation($change->ID, $change->Table, $id);
		}
	}

	$change->delete();
