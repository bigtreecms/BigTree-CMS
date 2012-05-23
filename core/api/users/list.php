<?
	/*
	|Name: Get List of Users|
	|Description: Gets an array of all existing BigTree users.|
	|Readonly: YES|
	|Level: 1|
	|Parameters:|
	|Returns:
		users: Array of User Objects|
	*/
	$admin->apiRequireLevel(1);
	echo BigTree::apiEncode(array("success" => true,"users" => $admin->getUsers()));
?>