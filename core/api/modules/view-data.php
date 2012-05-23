<?
	/*
	|Name: Get View Data|
	|Description: Retrieves parsed view data for a given view ID.|
	|Readonly: YES|
	|Level: 0|
	|Parameters: 
		view: View ID|
	|Returns:
		data: View Data Array|
	*/
	
	$view = $autoModule->getView($_POST["view"]);
	if ($view["options"]["sort_column"]) {
		$sort = $view["options"]["sort_column"]." ".$view["options"]["sort_direction"];
	} else {
		$sort = "id asc";
	}

	echo BigTree::apiEncode(array("success" => true,"data" => $autoModule->getParsedViewData($view,$sort)));
?>