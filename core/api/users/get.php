<?
	/*
	|Name: Get User|
	|Description: Get information on an existing BigTree user.|
	|Readonly: NO|
	|Level: 1|
	|Parameters: 
		token: API Token,
		id: User's Database ID|
	|Returns:
		user: User Object|
	*/

	$admin->requireAPIWrite();
	$admin->requireAPILevel(1);
	echo BigTree::apiEncode(array("success" => true,"user" => $admin->getUser($_POST["id"])));
?>