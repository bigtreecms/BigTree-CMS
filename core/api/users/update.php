<?
	/*
	|Name: Update User|
	|Description: Updates an existing BigTree user.  If no password is passed, the password is not updated.|
	|Readonly: NO|
	|Level: 1|
	|Parameters: 
		token: API Token,
		id: User's Database ID,
		name: Name,
		company: Company,
		phone: Phone Number,
		email: Email,
		password(optional): Password,
		level: User Access Level,
		permissions: Array of Module permissions (key is the module ID, value is "e" - editor, or "p" - publisher)|
	|Returns:
		user: User Object|
	*/

	$admin->apiRequireWrite();
	$admin->apiRequireLevel(1);
	
	$success = $admin->updateUser($_POST["id"],$_POST);
	
	if ($success) {
		echo BigTree::apiEncode(array("success" => true,"user" => $admin->getUser($success)));
	} else {
		echo BigTree::apiEncode(array("success" => false,"error" => "You may not update a user with a higher permission level."));
	}
?>