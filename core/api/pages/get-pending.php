<?
	/*
	|Name: Get Page with Pending Changes|
	|Description: Retrieves a page's information and parsed resources as well as unpublished changes.|
	|Readonly: YES|
	|Level: 0|
	|Parameters: 
		id: Page's Database ID|
	|Returns:
		page: Page Object|
	*/

	$page = $cms->getPendingPageById($_POST["id"]);
	if ($page) {
		$template = $cms->getTemplateById($page["template"]);
		if ($template["level"] > $admin->Level) {
			$page["template_locked"] = true;
		} else {
			$page["template_locked"] = false;
		}
		$page["resources"] = $cms->getPendingResourcesById($_POST["id"]);
		$page["sidebar"] = $cms->getParsedSidebar($page["sidebar"]);
		echo BigTree::apiEncode(array("success" => true,"page" => $page));
	} else {
		echo BigTree::apiEncode(array("success" => false,"error" => "Page not found."));
	}
?>