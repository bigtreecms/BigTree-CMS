<?
	/*
	|Name: Authenticate|
	|Description: Returns a temporary API Token for the authenticated user.|
	|Readonly: NO|
	|Level: 0|
	|Parameters: 
		email: Email Address,
		password: Password|
	|Returns:
		token: Temporary API Token|
	*/
	
	echo $admin->apiLogin($_POST["email"],$_POST["password"]);
?>