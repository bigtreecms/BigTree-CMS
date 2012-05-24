<?
	/*
	|Name: Update Setting|
	|Description: Updates an existing BigTree setting.|
	|Readonly: NO|
	|Level: 2|
	|Parameters: 
		setting: Current Setting ID,
		id: New Setting ID,
		title: Name of the Setting,
		description: Description,
		type: Type of Setting (see Types of Settings),
		locked: Lock to Developers Only ("on" or "")|
	|Returns:
		setting: Setting Object|
	*/
	
	$admin->apiRequireWrite();
	$admin->apiRequireLevel(2);
	
	$success = $admin->updateSetting($_POST["setting"],$_POST);
	if ($success) {
		echo BigTree::apiEncode(array("success" => true,"setting" => $admin->getSettingById($_POST["id"])));
	} else {
		echo BigTree::apiEncode(array("success" => true,"error" => "A setting already exists with that id."));
	}
?>