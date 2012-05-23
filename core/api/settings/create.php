<?
	/*
	|Name: Create Setting|
	|Description: Creates a new BigTree setting.|
	|Readonly: NO|
	|Level: 2|
	|Parameters: 
		id: Setting ID,
		title: Name of the Setting,
		description: Description,
		type: Type of Setting (see Types of Settings),
		locked: Lock to Developers Only ("on" or "")|
	|Returns:
		setting: Setting Object|
	*/
	
	$admin->requireAPIWrite();
	$admin->requireAPILevel(2);
	
	$success = $admin->createSetting($_POST);
	if ($success) {
		echo BigTree::apiEncode(array("success" => true,"setting" => $admin->getSettingById($_POST["id"])));
	} else {
		echo BigTree::apiEncode(array("success" => true,"error" => "A setting already exists with that id."));
	}
?>