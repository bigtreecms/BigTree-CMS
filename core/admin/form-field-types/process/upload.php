<?
	$storage = new BigTreeStorage;
	
	// We're processing a file.
	if (!$field["options"]["image"]) {
		if ($field["file_input"]["tmp_name"]) {
			$value = $storage->upload($field["file_input"]["tmp_name"],$name,$field["options"]["directory"]);
			
			if (!$value) {
				if ($storage->DisabledFileError) {
					$fails[] = array("field" => $field["options"]["title"], "error" => "Could not upload file. The file extension is not allowed.");
				} else {
					$fails[] = array("field" => $field["options"]["title"], "error" => "Could not upload file. The destination is not writable.");
				}
			}
		} else {
			if ($error == 1 || $error == 2) {
				$fails[] = array("field" => $field["options"]["title"], "error" => "The file you uploaded ($name) was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
			} elseif ($error == 3) {
				$fails[] = array("field" => $field["options"]["title"], "error" => "The file upload failed ($name).");
			}
			
			$value = $data["currently_$key"];
		}
	// We're processing an image.
	} else {
		if ($temp_name) {
			include BigTree::path("admin/form-field-types/process/_photo-process.php");
		} else {
			$value = $data["currently_$key"];
			
			if ($error == 1 || $error == 2) {
				$fails[] = array("field" => $field["options"]["title"], "error" => "The file you uploaded ($name) was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
			} elseif ($error == 3) {
				$fails[] = array("field" => $field["options"]["title"], "error" => "The file upload failed ($name).");		
			// Maybe we used an existing file?
			} else {
				if (substr($value,0,11) == "resource://") {
					// It's technically a new file now, but we pulled it from resources so we might need to crop it.
					$resource = sqlescape(str_replace(array(STATIC_ROOT,WWW_ROOT),array("{staticroot}","{wwwroot}"),substr($value,11)));
					
					$r = $admin->getResourceByFile($resource);
					$r["file"] = str_replace(array("{wwwroot}",WWW_ROOT,"{staticroot}",STATIC_ROOT),SITE_ROOT,$r["file"]);
					$pinfo = BigTree::pathInfo($r["file"]);					
					
					// We're going to need to create a local copy if we need more 
					if ((is_array($field["options"]["crops"]) && count($field["options"]["crops"])) || (is_array($field["options"]["thumbs"]) && count($field["options"]["thumbs"]))) {
						$local_copy = SITE_ROOT."files/".uniqid("temp-").$pinfo["extension"];
						file_put_contents($local_copy,file_get_contents($r["file"]));
						
						$value = $storage->upload($local_copy,$pinfo["basename"],$field["options"]["directory"],false);
						$pinfo = BigTree::pathInfo($value);
					
						if (is_array($field["options"]["crops"])) {
							foreach ($field["options"]["crops"] as $crop) {
								// Make a square if the user forgot to enter one of the crop dimensions.
								if (!$crop["height"]) {
									$crop["height"] = $crop["width"];
								} elseif (!$crop["width"]) {
									$crop["width"] = $crop["height"];
								}
								$crops[] = array(
									"image" => $local_copy,
									"directory" => $field["options"]["directory"],
									"retina" => $field["options"]["retina"],
									"name" => $pinfo["basename"],
									"width" => $crop["width"],
									"height" => $crop["height"],
									"prefix" => $crop["prefix"],
									"thumbs" => $crop["thumbs"]
								);
							}
						}
						
						if (is_array($field["options"]["thumbs"])) {
							foreach ($field["options"]["thumbs"] as $thumb) {
								$temp_thumb = SITE_ROOT."files/".uniqid("temp-").".".$pinfo["extension"];
								BigTree::createThumbnail($local_copy,$temp_thumb,$thumb["width"],$thumb["height"],$field["options"]["retina"],$field["options"]["grayscale"]);
								// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
								$storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$field["options"]["directory"]);
							}
						}
					// If we don't have any crops or thumbnails we don't need to change the location of the file, so just use the existing one.
					} else {
						$value = str_replace(SITE_ROOT,"{staticroot}",$r["file"]);
					}
				} else {
					$value = str_replace(array(STATIC_ROOT,WWW_ROOT),array("{staticroot}","{wwwroot}"),$value);
				}
			}
		}
	}
	
	// For callouts
	if (!$value && $callout_resources) {
		if (isset($data[$key])) {
			$value = $data[$key];
		}
	}
?>		