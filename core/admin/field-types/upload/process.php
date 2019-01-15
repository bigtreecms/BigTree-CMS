<?php
	// If a file upload error occurred, return the old data and set errors
	if ($field["file_input"]["error"] == 1 || $field["file_input"]["error"] == 2) {
		$bigtree["errors"][] = array("field" => $field["title"], "error" => "The file you uploaded (".$field["file_input"]["name"].") was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
		$field["output"] = $field["input"];
	} elseif ($field["file_input"]["error"] == 3) {
		$bigtree["errors"][] = array("field" => $field["title"], "error" => "The file upload failed (".$field["file_input"]["name"].").");
		$field["output"] = $field["input"];
	} else {
		if (is_uploaded_file($field["file_input"]["tmp_name"])) {
			$storage = new BigTreeStorage;
			$field["output"] = $storage->store($field["file_input"]["tmp_name"],$field["file_input"]["name"],$field["settings"]["directory"]);

			if (!$field["output"]) {
				if ($storage->DisabledFileError) {
					$bigtree["errors"][] = array("field" => $field["title"], "error" => "Could not upload file. The file extension is not allowed.");
				} else {
					$bigtree["errors"][] = array("field" => $field["title"], "error" => "Could not upload file. The destination is not writable.");
				}
			}
		} else {
			$field["output"] = $field["input"];
		}
	}
	