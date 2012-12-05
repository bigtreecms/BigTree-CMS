<?	
	function gatherModuleInformation($mid) {
		global $admin,$cms,$tables,$templates,$settings,$feeds,$class_files,$required_files,$other_files;
		$m = $admin->getModule($mid);
		$actions = $admin->getModuleActions($m["id"]);
		// Get all the tables of the module's actions.
		foreach ($actions as $action) {
			if ($action["form"] || $action["view"]) {
				if ($action["form"]) {
					$auto = BigTreeAutoModule::getForm($action["form"]);
					foreach ($auto["fields"] as $field) {
						if ($field["type"] == "list" && $field["list_type"] == "db") {
							if (!in_array($field["pop-table"]."#structure",$tables) && substr($field["pop-table"],0,8) != "bigtree_") {
								$tables[] = $field["pop-table"]."#structure";
							}
						}
						if ($field["type"] == "many_to_many") {
							if (!in_array($field["mtm-connecting-table"]."#structure",$tables) && substr($field["mtm-connecting-table"],0,8) != "bigtree_") {
								$tables[] = $field["mtm-connecting-table"]."#structure";
							}
							if (!in_array($field["mtm-other-table"]."#structure",$tables) && substr($field["mtm-other-table"],0,8) != "bigtree_") {
								$tables[] = $field["mtm-other-table"]."#structure";
							}
						}
					}
				} else {
					$auto = BigTreeAutoModule::getView($action["view"]);
				}

				if (!in_array($auto["table"]."#structure",$tables)) {
					$tables[] = $auto["table"]."#structure";
				}
			}
		}
		
		// Get all related feeds.
		foreach ($tables as $tinfo) {
			list($table,$type) = explode("#",$tinfo);
			$q = sqlquery("SELECT * FROM bigtree_feeds WHERE `table` = '$table'");
			while ($f = sqlfetch($q)) {
				$feeds[$f["id"]] = $f["name"];
			}
		}
		
		// Search the class files directory to see if one exists in there with our route.
		if (file_exists(SERVER_ROOT."custom/inc/modules/".$m["route"].".php")) {
			$class_files[$mid] = "custom/inc/modules/".$m["route"].".php";
		}
		
		// Search the class files directory to see if one exists in there with our route.
		if (file_exists(SERVER_ROOT."custom/inc/required/".$m["route"].".php")) {
			$required_files[] = "custom/inc/required/".$m["route"].".php";
		}
		
		$other_files = array_merge($other_files,array_merge(traverseOtherFiles(SERVER_ROOT."custom/admin/modules/".$m["route"]."/"),traverseOtherFiles(SERVER_ROOT."custom/admin/ajax/".$m["route"]."/")));
		// Get images
		$other_files = array_merge($other_files,traverseOtherFiles(SERVER_ROOT."custom/admin/images/".$m["route"]."/"));
		
		if (file_exists(SERVER_ROOT."custom/admin/css/".$m["route"].".css"))
			$other_files[] = "custom/admin/css/".$m["route"].".css";
		if (file_exists(SERVER_ROOT."custom/admin/js/".$m["route"].".js"))
			$other_files[] = "custom/admin/js/".$m["route"].".js";
	}
			
	function traverseOtherFiles($directory) {
		$files = array();
		if (file_exists($directory)) {
			$d = opendir($directory);
			while ($r = readdir($d)) {
				if ($r != "." && $r != "..") {
					if (is_dir($directory.$r)) {
						$files = array_merge($files,traverseOtherFiles($directory.$r."/"));				
					} else {
						$files[] = str_replace(SERVER_ROOT,"",$directory.$r);
					}
				}
			}
		}
		return $files;
	}
	
	$tables = array();
	$templates = array();
	$settings = array();
	$feeds = array();
	$field_types = array();
	$class_files = array();
	$required_files = array();
	$other_files = array();
?>