<?
	/*
	|Name: Delete Page|
	|Description: Deletes a page or requests deletion if an user is an editor.|
	|Readonly: NO|
	|Level: 0|
	|Parameters: 
		id: Page's Database ID|
	|Returns:
		status: "APPROVED" for immediate change or "PENDING"|
	*/
	
	$p = $admin->getPageAccessLevel($_POST["id"]);
	if (!$p) {
		echo BigTree::apiEncode(array("success" => false,"error" => "You do not have permission to edit this page."));
	} else {
		$admin->deletePage($_POST["id"]);
		if ($p == "e")
			$status = "PENDING";
		else
			$status = "APPROVED";
		echo BigTree::apiEncode(array("success" => true,"status" => $status));
	}
?>