<?
	BigTree::globalizePOSTVars();
	
	// Make sure at least one field is in this view.
	$ok = false;
	foreach ($_POST["fields"] as $key => $field) {
		if ($field["title"]) {
			$ok = true;
		}
	}
	
	if (!$ok) {
		$_SESSION["developer"]["designer_errors"]["fields"] = true;
		$_SESSION["developer"]["saved_module"] = $_POST;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	foreach ($actions as $action => $state) {
		if ($action == "approve") {
			sqlquery("ALTER TABLE `$table` ADD COLUMN approved CHAR(2) NOT NULL");
		} elseif ($action == "feature") {
			sqlquery("ALTER TABLE `$table` ADD COLUMN featured CHAR(2) NOT NULL");
		} elseif ($action == "archive") {
			sqlquery("ALTER TABLE `$table` ADD COLUMN archived CHAR(2) NOT NULL");
		}
	}
	
	if ($type == "draggable") {
		sqlquery("ALTER TABLE `$table` ADD COLUMN position INT(11) NOT NULL");
	}	
	
	// Let's create the view - we're decoding options here because it's already encoded but that'd be weird to assume in the class.
	$view_id = $admin->createModuleView($module,$title,$description,$table,$type,json_decode($options,true),$fields,$actions);
	$admin->createModuleAction($module,"View $title",$route,"on","list",0,$view_id,0,0,1);
		
	BigTree::redirect(DEVELOPER_ROOT."modules/designer/complete/?module=$module");
?>