<?
	/*
	|Name: Update Page|
	|Description: Updates a page or requests change if an user is an editor.  Does not presently support cropping.|
	|Readonly: NO|
	|Level: 0|
	|Parameters: 
		id: Page's ID,
		parent: Page's Parent ID,
		in_nav: Visible in Navigation (eitehr empty or "on"),
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
		if (!isset($_POST["id"]) || $_POST["id"] === "") {
			echo BigTree::apiEncode(array("success" => false,"error" => "You did not provide a Page ID."));
			die();
		}
		$page = $cms->getPageById($_POST["id"]);
		
		if ($_POST["parent"] == 0) {
			if ($admin->Level < 2) {
				if ($_POST["in_nav"] && (!$page["in_nav"] || $page["parent"] != $_POST["parent"])) {
					$warnings[] = "Non-Developer attempted to place page in main level navigation.  Moved to hidden.";
					$_POST["in_nav"] = "";
				}
			}
		}
		
		// Make sure we have a full data set, even if they only submitted a few things.
		foreach ($page as $key => $val) {
			if (!isset($_POST[$key]))
				$_POST[$key] = $val;
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
		
		// Parse the sidelets
		$sidelets = array();
		foreach ($_POST["sidelets"] as $number => $data) {
			
			// Super big hack to get file data in the right place
			$file_data = array();
			foreach ($_FILES["sidelets"]["name"][$number] as $key => $val) {
				$file_data["name"][$key] = $val;
			}
			foreach ($_FILES["sidelets"]["tmp_name"][$number] as $key => $val) {
				$file_data["tmp_name"][$key] = $val;
			}
			
			$sidelet = array();
			$sdata = $cms->getSideletById($data["type"]);
			$sresources = unserialize($sdata["resources"]);
			
			foreach ($sresources as $options) {
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
					$sidelet[$key] = $value;
			}
			$sidelet["type"] = $data["type"];
			$sidelets[] = $sidelet;
		}
			
		$_POST["sidelets"] = $sidelets;
		
		if ($p == "e") {
			$id = $admin->submitPageChange($_POST["id"],$_POST);
			$status = "PENDING";
		} else {
			$id = $admin->updatePage($_POST["id"],$_POST);
			$status = "APPROVED";
		}
		echo BigTree::apiEncode(array("success" => true,"id" => $id,"status" => $status,"warnings" => $warnings));
	}
?>