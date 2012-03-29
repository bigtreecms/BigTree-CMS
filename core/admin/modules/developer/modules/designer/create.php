<?
	BigTree::globalizePOSTVars();
	
	$errors = array();
	
	// Check if the table exists
	$table_exists = false;
	$q = sqlquery("SHOW TABLES");
	while ($f = sqlfetch($q)) {
		$tname = $f["Tables_in_".$GLOBALS["config"]["db"]["name"]];
		if ($tname == $table) {
			$table_exists = true;
		}
	}
	if ($table_exists) {
		$errors["table"] = "The table you chose already exists.";
	}
	
	// Check if the class name exists
	if (class_exists($class)) {
		$errors["class"] = "The class name you chose already exists.";
	}
	
	if (count($errors)) {
		$_SESSION["developer"]["designer_errors"] = $errors;
		$_SESSION["developer"]["saved_module"] = $_POST;
		header("Location: ../");
		die();
	}
		
	if ($group_new) {
		$group = $admin->createModuleGroup($group_new,"on");
	} else {
		$group = $group_existing;
	}
	
	$id = $admin->createModule($name,$group,$class,$table,$gbp);
	
	// Create the table.
	sqlquery("CREATE TABLE `$table` (`id` int(11) NOT NULL auto_increment, PRIMARY KEY  (`id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin");
	
	header("Location: ../form/$id/$table/");
?>