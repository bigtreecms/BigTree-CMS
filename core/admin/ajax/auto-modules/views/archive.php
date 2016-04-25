<?php
	namespace BigTree;

	include "_setup.php";
	
	if ($item["archived"]) {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now unarchived.";
			
			if (is_numeric($id)) {
				SQL::update($table,$id,array("archived" => ""));
			} else {
				\BigTreeAutoModule::updatePendingItemField(substr($id,1),"archived","");
			}
		}
	} else {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now archived.";
			
			if (is_numeric($id)) {
				SQL::update($table,$id,array("archived" => "on"));
			} else {
				\BigTreeAutoModule::updatePendingItemField(substr($id,1),"archived","on");
			}
		}
	}
	
	include "_recache.php";
?>
BigTree.growl("<?=$module["name"]?>","<?=Text::translate($message, true)?>");