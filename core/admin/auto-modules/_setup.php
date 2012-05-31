<?	
	$in_module = true;
	
	$mpage = ADMIN_ROOT.$module["route"]."/";
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
		$breadcrumb[] = array("link" => "modules/".$mgroup["route"]."/", "title" => $mgroup["name"]);
	}
	$breadcrumb[] = array("link" => $module["route"], "title" => $module["name"]);
	
	// Module Actions
	$actions = $admin->getModuleNavigation($module);
?>