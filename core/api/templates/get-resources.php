<?
	/*
	|Name: Get Template Resources|
	|Description: Retrieves a template's resource list.|
	|Readonly: YES|
	|Level: 0|
	|Parameters: 
		id: Template's Database ID|
	|Returns:
		page: Template Resource Object|
	*/

	$tsources = $cms->getTemplateResourcesById($_POST["id"]);
	if ($tsources) {
		echo BigTree::apiEncode(array("success" => true,"resources" => $tsources));
	} else {
		echo BigTree::apiEncode(array("success" => false,"error" => "Template not found."));
	}
?>