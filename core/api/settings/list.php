<?
	/*
	|Name: Get List of Settings|
	|Description: Gets an array of all BigTree settings.|
	|Readonly: YES|
	|Level: 1|
	|Parameters:|
	|Returns:
		settings: Array of Setting Objects|
	*/
	$admin->apiRequireLevel(1);
	$s = $admin->getAllSettings();
	echo BigTree::apiEncode(array("success" => true,"settings" => $s));
?>