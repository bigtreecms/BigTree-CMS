<?
	/*
	|Name: Get Edit Form for View|
	|Description: Retrieves the associated edit form for the specified view and optionally retrieves information on the provided item ID.|
	|Readonly: YES|
	|Level: 0|
	|Parameters: 
		view: View ID,
		item: Item ID|
	|Returns:
		form: Form Array,
		item: Item Array|
	*/
	
	$edit = "edit";
	$view = $autoModule->getView($_POST["view"]);
	$module = $autoModule->getModuleForView($view);
	if ($view["suffix"])
		$edit .= "-".$view["suffix"];
	
	$e = sqlfetch(sqlquery("select * from bigtree_module_actions where module = '$module' and route = '$edit'"));
	if ($e["form"]) {
		$form = $autoModule->getForm($e["form"]);
		
		if ($_POST["item"]) {
			$module = new BigTreeModule;
			$module->Table = $form["table"];
			$item = $module->get($_POST["item"]);
		} else {
			$item = false;
		}
		
		echo BigTree::apiEncode(array("success" => true,"form" => $form,"item" => $item));
	} else {
		echo BigTree::apiEncode(array("success" => false,"error" => "Could not find edit form for given view."));
	}
?>