<?
	$storage = new BigTreeStorage;
	$failed = false;
		
	// Let's check the minimum requirements for the image first before we store it anywhere.
	$image_info = @getimagesize($temp_name);
	$iwidth = $image_info[0];
	$iheight = $image_info[1];
	$itype = $image_info[2];
	$channels = $image_info["channels"];

	// If the minimum height or width is not meant, do NOT let the image through.  Erase the change or update from the database.
	if ((isset($field["options"]["min_height"]) && $iheight < $field["options"]["min_height"]) || (isset($field["options"]["min_width"]) && $iwidth < $field["options"]["min_width"])) {
		$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "Image uploaded did not meet the minimum size of ".$field["options"]["min_width"]."x".$field["options"]["min_height"]);
		$failed = true;
	}
	
	// If it's not a valid image, throw it out!
	if ($itype != IMAGETYPE_GIF && $itype != IMAGETYPE_JPEG && $itype != IMAGETYPE_PNG) {
		$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" =>  "An invalid file was uploaded. Valid file types: JPG, GIF, PNG.");
		$failed = true;
	}
	
	// See if it's CMYK
	if ($channels == 4) {
		$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" =>  "A CMYK encoded file was uploaded. Please upload an RBG image.");
		$failed = true;
	}

	// See if we have enough memory for all our crops and thumbnails
	if (!$failed && ((is_array($field["options"]["crops"]) && count($field["options"]["crops"])) || (is_array($field["options"]["thumbs"]) && count($field["options"]["thumbs"])))) {
		if (is_array($field["options"]["crops"])) {
			foreach ($field["options"]["crops"] as $crop) {
				if ($field["options"]["retina"]) {
					$crop["width"] *= 2;
					$crop["height"] *= 2;
				}
				// We don't want to add multiple errors so we check if we've already failed
				if (!$failed && !BigTree::imageManipulationMemoryAvailable($temp_name,$crop["width"],$crop["height"],$iwidth,$iheight)) {
					$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.");
					$failed = true;
				}
			}
		}
		if (is_array($field["options"]["thumbs"])) {
			foreach ($field["options"]["thumbs"] as $thumb) {
				// We don't want to add multiple errors and we also don't want to waste effort getting thumbnail sizes if we already failed.
				if (!$failed) {
					if ($field["options"]["retina"]) {
						$thumb["width"] *= 2;
						$thumb["height"] *= 2;
					}
					$sizes = BigTree::getThumbnailSizes($temp_name,$thumb["width"],$thumb["height"]);
					if (!BigTree::imageManipulationMemoryAvailable($temp_name,$sizes[3],$sizes[4],$iwidth,$iheight)) {
						$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "Image uploaded is too large for the server to manipulate. Please upload a smaller version of this image.");
						$failed = true;
					}
				}
			}
		}
	}

	if (!$failed) {	
		// Do EXIF Image Rotation
		$already_created_first_copy = false;
		if ($itype == IMAGETYPE_JPEG && function_exists("exif_read_data")) {
			$exif = @exif_read_data($temp_name);
			$o = $exif['Orientation'];
			if ($o == 3 || $o == 6 || $o == 8) {
				$first_copy = SITE_ROOT."files/".uniqid("temp-").".jpg";
				$source = imagecreatefromjpeg($temp_name);
				
				if ($o == 3) {
					$source = imagerotate($source,180,0);
				} elseif ($o == 6) {
					$source = imagerotate($source,270,0);
				} else {
					$source = imagerotate($source,90,0);
				}
				
				imagejpeg($source,$first_copy);
				imagedestroy($source);
				$already_created_first_copy = true;
			}
		}

		// Make a temporary copy to be used for thumbnails and crops.
		$itype_exts = array(IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif");
		
		if (!$already_created_first_copy) {
			$first_copy = $temp_name;
		}
		
		// Let's crush this png.
		if ($itype == IMAGETYPE_PNG && $storage->optipng) {
			$first_copy = SITE_ROOT."files/".uniqid("temp-").".png";
			move_uploaded_file($temp_name,$first_copy);
			
			exec($storage->optipng." ".$first_copy);
		}
		// Let's crush the gif and see if we can make it a PNG.
		if ($itype == IMAGETYPE_GIF && $storage->optipng) {
			$first_copy = SITE_ROOT."files/".uniqid("temp-").".gif";
			move_uploaded_file($temp_name,$first_copy);
			
			exec($storage->optipng." ".$first_copy);
			if (file_exists(substr($first_copy,0,-3)."png")) {
				unlink($first_copy);
				$first_copy = substr($first_copy,0,-3)."png";
				$name_parts = BigTree::pathInfo($name);
				$name = $name_parts["filename"].".png";
			}
			
		}
		// Let's trim the jpg.
		if (!$already_created_first_copy && $itype == IMAGETYPE_JPEG && $storage->jpegtran) {
			$first_copy = SITE_ROOT."files/".uniqid("temp-").".jpg";
			move_uploaded_file($temp_name,$first_copy);
			
			exec($storage->jpegtran." -copy none -optimize -progressive $first_copy > $first_copy-trimmed");
			unlink($first_copy);
			$first_copy = $first_copy."-trimmed";
		}
		
		list($iwidth,$iheight,$itype,$iattr) = getimagesize($first_copy);
		
		$temp_copy = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
		BigTree::copyFile($first_copy,$temp_copy);
		
		// Upload the original to the proper place.
		$field["output"] = $storage->store($first_copy,$name,$field["options"]["directory"]);
 		
 		// If the upload service didn't return a value, we failed to upload it for one reason or another.
 		if (!$field["output"]) {
 			if ($storage->DisabledFileError) {
				$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "Could not upload file. The file extension is not allowed.");
			} else {
				$bigtree["errors"][] = array("field" => $field["options"]["title"], "error" => "Could not upload file. The destination is not writable.");
			}
			unlink($temp_copy);
			unlink($first_copy);
			$failed = true;
		// If we did upload it successfully, check on thumbs and crops.
		} else { 
			// Get path info on the file.
			$pinfo = BigTree::pathInfo($field["output"]);
		
			// Handle Crops
			foreach ($field["options"]["crops"] as $crop) {
				$cwidth = $crop["width"];
				$cheight = $crop["height"];
				
				// Check to make sure each dimension is greater then or equal to, but not both equal to the crop.
				if (($iheight >= $cheight && $iwidth > $cwidth) || ($iwidth >= $cwidth && $iheight > $cheight)) {
					// Make a square if for some reason someone only entered one dimension for a crop.
					if (!$cwidth) {
						$cwidth = $cheight;
					} elseif (!$cheight) {
						$cheight = $cwidth;
					}
					$bigtree["crops"][] = array(
						"image" => $temp_copy,
						"directory" => $field["options"]["directory"],
						"retina" => $field["options"]["retina"],
						"name" => $pinfo["basename"],
						"width" => $cwidth,
						"height" => $cheight,
						"prefix" => $crop["prefix"],
						"thumbs" => $crop["thumbs"],
						"grayscale" => $crop["grayscale"]
					);
				// If it's the same dimensions, let's see if they're looking for a prefix for whatever reason...
				} elseif ($iheight == $cheight && $iwidth == $cwidth) {
					// See if we want thumbnails
					if (is_array($crop["thumbs"])) {
						foreach ($crop["thumbs"] as $thumb) {
							// Create a temporary thumbnail of the image on the server before moving it to it's destination.
							$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
							BigTree::createThumbnail($temp_copy,$temp_thumb,$thumb["width"],$thumb["height"],$field["options"]["retina"],$thumb["grayscale"]);
							// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
							$storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$field["options"]["directory"]);
						}
					}
					
					$storage->store($temp_copy,$crop["prefix"].$pinfo["basename"],$field["options"]["directory"],false);
				}
			}
			
			// Handle thumbnailing
			if (is_array($field["options"]["thumbs"])) {
				foreach ($field["options"]["thumbs"] as $thumb) {
					$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
					BigTree::createThumbnail($temp_copy,$temp_thumb,$thumb["width"],$thumb["height"],$field["options"]["retina"],$thumb["grayscale"]);
					// We use replace here instead of upload because we want to be 100% sure that this file name doesn't change.
					$storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],$field["options"]["directory"]);
				}
			}
			
			// If we don't have any crops, get rid of the temporary image we made.
			if (!count($bigtree["crops"])) {
				unlink($temp_copy);
			}
		}
	// We failed, keep the current value.
	} else {
		$field["output"] = $field["input"];
	}
?>