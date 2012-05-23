<?
	/*
	|Name: Delete User|
	|Description: Deletes an existing BigTree user.|
	|Readonly: NO|
	|Level: 1|
	|Parameters: 
		token: API Token,
		id: User's Database ID|
	|Returns:|
	*/
	
	$admin->requireAPIWrite();
	$admin->requireAPILevel(1);
	
	$success = $admin->deleteUser($_POST["id"]);
	
	if ($success) {
		echo BigTree::apiEncode(array("success" => true));
	} else {
		echo BigTree::apiEncode(array("success" => false,"error" => "You may not delete a user with a higher permission level."));
	}
?>