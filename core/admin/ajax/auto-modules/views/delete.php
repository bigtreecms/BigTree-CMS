<?php

	include '_setup.php';

	// If you made this pending item, you should be allowed to delete it, or if you're a publisher of the module.
	if ($access_level != 'p' && $current_item['owner'] != $admin->ID) {
	    echo 'BigTree.growl("'.$module['name'].'","You don\'t have permission to delete this item.");';
	} else {
	    echo 'BigTree.growl("'.$module['name'].'","Deleted Item");';

	    if (substr($id, 0, 1) == 'p') {
	        BigTreeAutoModule::deletePendingItem($table, substr($id, 1));
	    } else {
	        BigTreeAutoModule::deleteItem($table, $id);
	    }
	}
?>