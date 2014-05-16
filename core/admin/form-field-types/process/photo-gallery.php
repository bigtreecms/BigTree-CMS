<?
	$photo_gallery = array();
	if (is_array($field["input"])) {
		foreach ($field["input"] as $photo_count => $data) {
			// Existing Data
			if ($data["image"]) {
				$data["caption"] = BigTree::safeEncode($data["caption"]);
				$photo_gallery[] = $data;
			// Uploaded File
			} elseif ($field["file_input"][$photo_count]["image"]["name"]) {
				$field_copy = $field;
				$field_copy["file_input"] = $field["file_input"][$photo_count]["image"];

				$file = $admin->processImageUpload($field_copy);
				if ($file) {
					$photo_gallery[] = array("caption" => BigTree::safeEncode($data["caption"]),"image" => $file);
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
					$photo_gallery[] = array("caption" => BigTree::safeEncode($data["caption"]),"image" => $file);
				}
			}
		}
	}
	
	$field["output"] = $photo_gallery;
?>