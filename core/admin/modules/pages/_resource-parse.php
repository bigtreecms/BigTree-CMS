<?
	// Parse the resources
	$template = $cms->getTemplate($_POST["template"]);
	$data = $_POST["resources"];
	$file_data = $_FILES["resources"];
	foreach ($template["resources"] as $options) {
		$key = $options["id"];
		$type = $options["type"];
		$options["directory"] = "files/pages/";
		$tpath = BigTree::path("admin/form-field-types/process/$type.php");
		
		$no_process = false;
		// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
		if (file_exists($tpath)) {
			include $tpath;
		} else {
			$value = htmlspecialchars(htmlspecialchars_decode($data[$key]));
		}
		
		if (!BigTreeForms::validate($value,$options["validation"])) {
			$error = $options["error_message"] ? $options["error_message"] : BigTreeForms::errorMessage($value,$options["validation"]);
			$fails[] = array(
				"field" => $options["title"],
				"error" => $error
			);
		}
		
		$value = $admin->autoIPL($value);
		if (!$no_process) {
			$resources[$key] = $value;
		}
	}

	$_POST["resources"] = $resources;
?>