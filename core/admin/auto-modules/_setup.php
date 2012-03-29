<?	
	$in_module = true;
	
	$mpage = $admin_root.$module["route"]."/";
	$mgroup = false;
	
	// Calculate related modules
	if ($module["group"]) {
		$mgroup = $admin->getModuleGroup($module["group"]);
		$other = $admin->getModulesByGroup($module["group"]);
		if (count($other) > 1) {
			$subnav = array();
			foreach ($other as $more) {
				$subnav[] = array("title" => $more["name"], "link" => $more["route"]."/");
			}
		}
	}
	
	// Calculate breadcrumb
	$breadcrumb = array(
		array("link" => "modules/","title" => "Modules")
	);
	if ($mgroup) {
		$breadcrumb[] = array("link" => "modules/", "title" => $mgroup["name"]);
	}
	$breadcrumb[] = array("link" => $module["route"], "title" => $module["name"]);
	
	// Sub Nav
	$actions = $admin->getModuleNavigation($module);
?>