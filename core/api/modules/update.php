<?
	/*
	|Name: Update a Module Item|
	|Description: Updates an entry in the database for a given module form.|
	|Readonly: NO|
	|Level: 0|
	|Parameters: 
		form: Form ID,
		item: Item Object|
	|Returns:
		id: Page ID or Change ID,
		status: "APPROVED" for immediate change or "PENDING"|
	*/
	
	$form = $autoModule->getForm($_POST["form"]);
	$module = $autoModule->getModuleForForm($form);
	$parser = new BigTreeForms($form["table"]);
	$a = $admin->checkAccess($module);
	
	if (!$a) {
		echo BigTree::apiEncode(array("success" => false,"error" => "User does not have access to this module."));
		die();
	}
	
	$item = $_POST["item"]["id"];
	unset($_POST["item"]["id"]);
	$data = $parser->sanitizeFormDataForDB($_POST["item"]);
	
	if ($a == "e") {
		$id = $autoModule->submitChange($module,$form["table"],$item,$data);
		$status = "PENDING";
	}
	
	if ($a == "p") {
		$id = $autoModule->updateItem($form["table"],$item,$data);
		$status = "APPROVED";
	}
	
	echo BigTree::apiEncode(array("success" => true,"id" => $id,"status" => $status));
?>