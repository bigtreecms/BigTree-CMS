<?
	/*
	|Name: Create User|
	|Description: Creates a new BigTree user.|
	|Readonly: NO|
	|Level: 1|
	|Parameters: 
		token: API Token,
		name: Name,
		company: Company,
		phone: Phone Number,
		email: Email,
		password: Password,
		level: User Access Level,
		permissions: Array of Module permissions (key is the module ID, value is "e" - editor, or "p" - publisher)|
	|Returns:
		user: User Object|
	*/
	
	$admin->requireAPIWrite();
	$admin->requireAPILevel(1);
	
	$id = $admin->createUser($_POST);
	echo BigTree::apiEncode(array("success" => true,"user" => $admin->getUser($id)));
?>