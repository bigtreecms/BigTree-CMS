<?php
	namespace BigTree;
	
	CSRF::verify();
	
	// Make sure at least one field is in this view.
	$ok = false;
	$table = $_POST["table"];
	
	foreach ($_POST["fields"] as $key => $field) {
		if ($field["title"]) {
			$ok = true;
		}
	}
	
	if (!$ok) {
		$_SESSION["developer"]["designer_errors"]["fields"] = true;
		$_SESSION["developer"]["saved_view"] = $_POST;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	foreach ($_POST["actions"] as $action => $state) {
		if ($action == "approve") {
			SQL::query("ALTER TABLE `$table` ADD COLUMN `approved` CHAR(2) NOT NULL");
			SQL::query("ALTER TABLE `$table` ADD INDEX `approved` (`approved`)");
		} elseif ($action == "feature") {
			SQL::query("ALTER TABLE `$table` ADD COLUMN `featured` CHAR(2) NOT NULL");
			SQL::query("ALTER TABLE `$table` ADD INDEX `featured` (`featured`)");
		} elseif ($action == "archive") {
			SQL::query("ALTER TABLE `$table` ADD COLUMN `archived` CHAR(2) NOT NULL");
			SQL::query("ALTER TABLE `$table` ADD INDEX `archived` (`archived`)");
		}
	}
	
	if ($_POST["type"] == "draggable") {
		SQL::query("ALTER TABLE `$table` ADD COLUMN `position` INT(11) NOT NULL");
		SQL::query("ALTER TABLE `$table` ADD INDEX `position` (`position`)");
	}
	
	// Let's create the view - we're decoding options here because it's already encoded but that'd be weird to assume in the class.
	$view = ModuleView::create($_POST["module"], $_POST["title"], $_POST["description"], $table, $_POST["type"],
							   json_decode($_POST["options"], true), $_POST["fields"], $_POST["actions"]);
	
	ModuleAction::create($_POST["module"], "View ".$_POST["title"], "", "on", "list", $view->ID, 0, 1);
	Router::redirect(DEVELOPER_ROOT."modules/designer/complete/?module=".$_POST["module"]);
	