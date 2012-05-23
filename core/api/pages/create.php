<?
	/*
	|Name: Create Page|
	|Description: Creates a page or requests creation if an user is an editor.  Does not presently support cropping.|
	|Readonly: NO|
	|Level: 0|
	|Parameters: 
		parent: Page's Parent ID,
		nav_title: Navigation Title,
		route: Navigation Route (auto-generates if left empty),
		publish_at: Publishing Date (leave empty to publish immediately),
		title: Page Title,
		template: Page Template ID (use templates/list to retrieve template options),
		external: External Link (overrides page template),
		new_window: Open In New Window (either empty or "on"),
		resources: Page Resources Array,
		meta_keywords: Meta Keywords,
		meta_description: Meta Description,
		permissions: Page Permissions Array|
	|Returns:
		id: Page ID or Change ID,
		status: "APPROVED" for immediate change or "PENDING",
		warnings: Page Warnings Array|
	*/
	
	$p = $admin->getPageAccessLevel($_POST["id"]);
	if (!$p) {
		echo BigTree::apiEncode(array("success" => false,"error" => "You do not have permission to edit this page."));
	} else {
		$warnings = array();
		
		if (!isset($_POST["parent"])) {
			$_POST["parent"] = 0;
			$warnings[] = "Parent ID not supplied, using 0.";
		}
		if ($_POST["parent"] == 0) {
			if ($admin->Level < 2) {
				if ($_POST["in_nav"]) {
					$warnings[] = "Non-Developer attempted to place page in main level navigation.  Moved to hidden.";
					$_POST["in_nav"] = "";
				}
			}
		}
		if (!$_POST["template"] && !$_POST["external"]) {
			echo BigTree::apiEncode(array("success" => false,"error" => "You did not choose a template for this page."));
			die();
		}
		
		// Parse the resources
		$template = $_POST["template"];
		$tsources = $cms->getTemplateResourcesById($template);
		$data = $_POST["resources"];
		$file_data = $_FILES["resources"];
		foreach ($tsources as $options) {
			$key = $options["id"];
			$type = $options["type"];
			$options["directory"] = "files/pages/";
			$tpath = bigtree_path("admin/form-field-types/process/$type.php");
			
			$no_process = false;
			// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
			if (file_exists($tpath)) {
				include $tpath;
			} else {
				$value = htmlspecialchars($data[$key]);
			}
			if (!$no_process)
				$resources[$key] = $value;
		}
	
		$_POST["resources"] = $resources;
		
		if ($p == "e") {
			$id = $admin->createPendingPage($_POST);
			$status = "PENDING";
		} else {
			$id = $admin->createPage($_POST);
			$status = "APPROVED";
		}
		echo BigTree::apiEncode(array("success" => true,"id" => $id,"status" => $status,"warnings" => $warnings));
	}
?>