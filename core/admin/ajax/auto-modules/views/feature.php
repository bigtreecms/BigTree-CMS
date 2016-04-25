<?php
	namespace BigTree;

	include "_setup.php";

	if ($item["featured"]) {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now unfeatured.";
			
			if (is_numeric($id)) {
				SQL::update($table,$id,array("featured" => ""));
			} else {
				\BigTreeAutoModule::updatePendingItemField(substr($id,1),"featured","");
			}
		}
	} else {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now featured.";
			
			if (is_numeric($id)) {
				SQL::update($table,$id,array("featured" => "on"));
			} else {
				\BigTreeAutoModule::updatePendingItemField(substr($id,1),"featured","on");
			}
		}
	}
	
	include "_recache.php";
?>
BigTree.growl("<?=$module["name"]?>","<?=Text::translate($message, true)?>");