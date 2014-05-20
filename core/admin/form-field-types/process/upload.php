<?
	// If a file upload error occurred, return the old data and set errors
	if ($field["file_input"]["error"] == 1 || $field["file_input"]["error"] == 2) {
		$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "The file you uploaded (".$field["file_input"]["name"].") was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
		$field["output"] = $field["input"];
	} elseif ($field["file_input"]["error"] == 3) {
		$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "The file upload failed (".$field["file_input"]["name"].").");
		$field["output"] = $field["input"];
	} else {
		// We're processing a file.
		if (!$field["options"]["image"]) {
			if (is_uploaded_file($field["file_input"]["tmp_name"])) {
				$storage = new BigTreeStorage;
				$field["output"] = $storage->store($field["file_input"]["tmp_name"],$field["file_input"]["name"],$field["options"]["directory"]);
	
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
			if (is_uploaded_file($field["file_input"]["tmp_name"])) {
				$file = $admin->processImageUpload($field);
				$field["output"] = $file ? $file : $field["input"];
			// Using an existing image or one from the Image Browser
			} else {
				$field["output"] = $field["input"];
	
				// We're trying to use an image from the Image Browser.
				if (substr($field["output"],0,11) == "resource://") {
					// It's technically a new file now, but we pulled it from resources so we might need to crop it.
					$resource = $admin->getResourceByFile(str_replace(array(STATIC_ROOT,WWW_ROOT),array("{staticroot}","{wwwroot}"),substr($field["output"],11)));
					$resource_location = str_replace(array("{wwwroot}",WWW_ROOT,"{staticroot}",STATIC_ROOT),SITE_ROOT,$resource["file"]);
					$pinfo = BigTree::pathInfo($resource_location);
	
					// Emulate a newly uploaded file
					$field["file_input"] = array("name" => $pinfo["basename"],"tmp_name" => SITE_ROOT."files/".uniqid("temp-").".img","error" => false);
					BigTree::copyFile($resource_location,$field["file_input"]["tmp_name"]);
	
					$file = $admin->processImageUpload($field);
					$field["output"] = $file ? $file : $field["input"];
				}
			}
		}
	}
?>