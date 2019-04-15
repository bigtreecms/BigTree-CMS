<?php
	namespace BigTree;

	/**
	 * @global array $item
	 * @global array $pending_entry
	 * @global int $id
	 * @global Module $module
	 * @global ModuleForm $form
	 * @global string $access_level
	 */

	include "_setup.php";
	
	// If you made this pending item, you should be allowed to delete it, or if you're a publisher of the module.
	if ($access_level != "p" && $pending_entry["owner"] != Auth::user()->ID) {
		$message = "You don't have permission to delete this item.";
	} else {
		$message = "Deleted Item";
		
		if (substr($id, 0, 1) == "p") {
			$form->deletePendingEntry(substr($id, 1));
		} else {
			$form->deleteEntry($id);
		}
	}
	
	Resource::deallocate($pending_entry["table"], $id);
?>
BigTree.growl("<?=$module->Name?>","<?=Text::translate($message, true)?>");