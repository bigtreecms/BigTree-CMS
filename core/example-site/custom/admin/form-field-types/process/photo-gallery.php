<?
	// Modified Photo Gallery to add Attribution and Link attributes
	$photo_gallery = array();
	if (is_array($field["input"])) {
		foreach ($field["input"] as $photo_count => $data) {
			// Existing Data
			if ($data["image"]) {
				$data["caption"] = BigTree::safeEncode($data["caption"]);
				$data["attribution"] = BigTree::safeEncode($data["attribution"]);
				$data["link"] = BigTree::safeEncode($data["link"]);
				$photo_gallery[] = $data;
			// Uploaded File
			} elseif ($field["file_input"][$photo_count]["image"]["name"]) {
				$field_copy = $field;
				$field_copy["file_input"] = $field["file_input"][$photo_count]["image"];

				$file = $admin->processImageUpload($field_copy);
				if ($file) {
					$photo_gallery[] = array("image" => $file,"caption" => BigTree::safeEncode($data["caption"]),"attribution" => BigTree::safeEncode($data["attribution"]),"link" => BigTree::safeEncode($data["link"]));
				}
			// File From Image Manager
			} elseif ($data["existing"]) {
				$data["existing"] = str_replace(WWW_ROOT,SITE_ROOT,$data["existing"]);
				$pinfo = BigTree::pathInfo($data["existing"]);

				$field_copy = $field;
				$field_copy["file_input"] = array("name" => $pinfo["basename"],"tmp_name" => SITE_ROOT."files/".uniqid("temp-").".img","error" => false);
				BigTree::copyFile($data["existing"],$field_copy["file_input"]["tmp_name"]);

				$file = $admin->processImageUpload($field_copy);
				if ($file) {
					$photo_gallery[] = array("image" => $file,"caption" => BigTree::safeEncode($data["caption"]),"attribution" => BigTree::safeEncode($data["attribution"]),"link" => BigTree::safeEncode($data["link"]));
				}
			}
		}
	}

	$field["output"] = $photo_gallery;
?>