<?
	/*
	|Name: Get Module List Grouped|
	|Description: Retrieves an ordered list of available module groups and their modules (that a user has access to).|
	|Readonly: YES|
	|Level: 0|
	|Parameters: |
	|Returns:
		groups: Array of Module Groups |
	*/
	
	$groups = array();
	$g = $admin->getModuleGroups();
	foreach ($g as $group) {
		$modules = $admin->getModulesByGroup($group["id"]);
		foreach ($modules as &$module) {
			$module["actions"] = $admin->getAutoModuleActions($module["id"]);
		}
		if (count($modules)) {
			$group["modules"] = $modules;
			$groups[] = $group;
		}
	}

	echo BigTree::apiEncode(array("success" => true,"groups" => $groups));
?>