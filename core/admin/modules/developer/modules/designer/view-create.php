<?
	BigTree::globalizePOSTVars();
	
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
	$view_id = $admin->createModuleView($title,$description,$table,$type,json_decode($options,true),$fields,$actions,$suffix);
	$admin->createModuleAction($module,"View $title",$route,"on","icon_small_home",0,$view_id);
		
	header("Location: ../complete/$module/");
	die();
?>