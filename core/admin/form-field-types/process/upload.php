<?
	$storage = new BigTreeStorage;
	
	// Set these up for _photo-process.php to use.
	$name = $field["file_input"]["name"];
	$temp_name = $field["file_input"]["tmp_name"];
	$error = $field["file_input"]["error"];
	
	// We're processing a file.
	if (!$field["options"]["image"]) {
		if ($temp_name) {
			$field["output"] = $storage->store($temp_name,$name,$field["options"]["directory"]);
			
			if (!$field["output"]) {
				if ($storage->DisabledFileError) {
					$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "Could not upload file. The file extension is not allowed.");
				} else {
					$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "Could not upload file. The destination is not writable.");
				}
			}
		} else {
			$field["output"] = $field["input"];
		}
	// We're processing an image.
	} else {
		// We uploaded a new image.
		if ($temp_name) {
			include BigTree::path("admin/form-field-types/process/_photo-process.php");
		} else {
			$field["output"] = $field["input"];

			// We're trying to use an image from the Image Browser.
			if (substr($field["output"],0,11) == "resource://") {
				// It's technically a new file now, but we pulled it from resources so we might need to crop it.
				$resource = $admin->getResourceByFile(str_replace(array(STATIC_ROOT,WWW_ROOT),array("{staticroot}","{wwwroot}"),substr($field["output"],11)));
				$resource_location = str_replace(array("{wwwroot}",WWW_ROOT,"{staticroot}",STATIC_ROOT),SITE_ROOT,$resource["file"]);
				$pinfo = BigTree::pathInfo($resource_location);
				$name = $pinfo["basename"];
				$temp_name = SITE_ROOT."files/".uniqid("temp-").".img";
				$error = false;
				
				BigTree::copyFile($resource_location,$temp_name);
				include BigTree::path("admin/form-field-types/process/_photo-process.php");
			}
		}
	}

	// If a file upload failed.
	if ($field["file_input"]["error"] == 1 || $field["file_input"]["error"] == 2) {
		$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "The file you uploaded (".$field["file_input"]["name"].") was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
	} elseif ($field["file_input"]["error"] == 3) {
		$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "The file upload failed (".$field["file_input"]["name"].").");
	}
?>		