<?php
	namespace BigTree;
	
	/**
	 * @global string $access_level
	 * @global ModuleForm $form
	 * @global int $id
	 * @global array $item
	 * @global Module $module
	 * @global string $table
	 * @global callable $run_publish_hook
	 */
	
	include "_setup.php";
	
	if ($item["featured"]) {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now unfeatured.";
			
			if (is_numeric($id)) {
				SQL::update($table, $id, ["featured" => ""]);
				$run_publish_hook(["featured" => ""]);
			} else {
				$form->updatePendingEntryField(substr($id, 1), "featured", "");
			}
		}
	} else {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now featured.";
			
			if (is_numeric($id)) {
				SQL::update($table, $id, ["featured" => "on"]);
				$run_publish_hook(["featured" => "on"]);
			} else {
				$form->updatePendingEntryField(substr($id, 1), "featured", "");
			}
		}
	}
	
	include "_recache.php";
?>
BigTree.growl("<?=$module->Name?>","<?=Text::translate($message, true)?>");