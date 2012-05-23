<?
	/*
	|Name: Get Setting|
	|Description: Get information on a BigTree setting.|
	|Readonly: YES|
	|Level: 1|
	|Parameters: 
		id: Setting ID|
	|Returns:
		setting: Setting Object|
	*/

	$admin->requireAPILevel(1);
	echo BigTree::apiEncode(array("success" => true,"setting" => $admin->getSettingById($_POST["id"])));
?>