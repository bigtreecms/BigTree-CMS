<?
	$storage = new BigTreeStorage;

	$photo_gallery = array();
	if (is_array($field["input"])) {
		foreach ($field["input"] as $photo_count => $data) {
			// If we have image data, it's a previously uploaded image we haven't changed, so just add it back to the photo gallery array.
			if ($data["image"]) {
				$data["caption"] = htmlspecialchars(htmlspecialchars_decode($data["caption"]));
				$photo_gallery[] = $data;
			// Otherwise, let's see if we have file information in the files array.
			} elseif ($field["file_input"][$photo_count]["image"]["name"]) {
				$name = $field["file_input"][$photo_count]["image"]["name"];
				$temp_name = $field["file_input"][$photo_count]["image"]["tmp_name"];
				$error = $field["file_input"][$photo_count]["image"]["error"];
	
				if ($error == 1 || $error == 2) {
					$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "The file you uploaded ($name) was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
				} elseif ($error == 3) {
					$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "The file upload failed ($name).");
				} else {
					include BigTree::path("admin/form-field-types/process/_photo-process.php");
					if (!$failed) {
						$photo_gallery[] = array("caption" => htmlspecialchars(htmlspecialchars_decode($data["caption"])),"image" => $field["output"]);
					}
				}
			} elseif ($data["existing"]) {
				$data["existing"] = str_replace(WWW_ROOT,SITE_ROOT,$data["existing"]);
				$pinfo = BigTree::pathInfo($data["existing"]);

				$name = $pinfo["basename"];
				$temp_name = SITE_ROOT."files/".uniqid("temp-").".img";
				$error = false;
				
				BigTree::copyFile($data["existing"],$temp_name);
				include BigTree::path("admin/form-field-types/process/_photo-process.php");
				
				if (!$failed) {	
					$photo_gallery[] = array("caption" => htmlspecialchars(htmlspecialchars_decode($data["caption"])), "image" => $field["output"]);
				}
			}
		}
	}
	
	$field["output"] = $photo_gallery;
?>