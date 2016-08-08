<?php
	namespace BigTree;
	
	$errors = array();
	
	// Check if the table exists
	if (SQL::tableExists($_POST["table"])) {
		$errors["table"] = Text::translate("The table you chose already exists.");
	}
	
	// Check if the class name exists
	if (class_exists($_POST["class"])) {
		$errors["class"] = Text::translate("The class name you chose already exists.");
	}
	
	if (count($errors)) {
		$_SESSION["developer"]["designer_errors"] = $errors;
		$_SESSION["developer"]["saved_module"] = $_POST;
		Router::redirect(DEVELOPER_ROOT."modules/designer/");
	}
	
	if ($_POST["group_new"]) {
		$group = ModuleGroup::create($_POST["group_new"]);
		$group_id = $group->ID;
	} else {
		$group_id = $_POST["group_existing"];
	}
	
	$module = Module::create($_POST["name"], $group_id, $_POST["class"], $_POST["table"], $_POST["gbp"], $_POST["icon"]);
	
	// Create the table.
	SQL::query("CREATE TABLE `".$_POST["table"]."` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) 
				ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
	
	Router::redirect(DEVELOPER_ROOT."modules/designer/form/?table=".urlencode($_POST["table"])."&module=".$module->ID);
	