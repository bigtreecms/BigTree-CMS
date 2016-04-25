<?php
	namespace BigTree;

	include "_setup.php";
	
	// If you made this pending item, you should be allowed to delete it, or if you're a publisher of the module.
	if ($access_level != "p" && $current_item["owner"] != $admin->ID) {
		$message = "You don't have permission to delete this item.";
	} else {
		$message = "Deleted Item";
		
		if (substr($id,0,1) == "p") {
			\BigTreeAutoModule::deletePendingItem($table,substr($id,1));
		} else {
			\BigTreeAutoModule::deleteItem($table,$id);
		}
	}
?>
BigTree.growl("<?=$module["name"]?>","<?=Text::translate($message, true)?>");