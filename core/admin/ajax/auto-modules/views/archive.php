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
	
	if ($item["archived"]) {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now unarchived.";
			
			if (is_numeric($id)) {
				SQL::update($table, $id, ["archived" => ""]);
				$run_publish_hook(["archived" => ""]);
			} else {
				$form->updatePendingEntryField(substr($id, 1), "archived", "");
			}
		}
	} else {
		if ($access_level != "p") {
			$message = "You don't have permission to perform this action.";
		} else {
			$message = "Item is now archived.";
			
			if (is_numeric($id)) {
				SQL::update($table, $id, ["archived" => "on"]);
				$run_publish_hook(["archived" => "on"]);
			} else {
				$form->updatePendingEntryField(substr($id, 1), "archived", "on");
			}
		}
	}
	
	include "_recache.php";
?>
BigTree.growl("<?=$module->Name?>","<?=Text::translate($message, true)?>");