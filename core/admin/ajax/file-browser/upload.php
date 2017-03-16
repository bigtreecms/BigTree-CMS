<?
	if (empty($_FILES)) {
?>
<html>
	<body>
		<script>
			parent.BigTreeFileManager.uploadError("The file(s) uploaded exceeded the maximum allowed size of <?=BigTree::formatBytes(BigTree::postMaxSize())?>", "");
		</script>
	</body>
</html>
<?
		die();
	}

	$admin->verifyCSRFToken();

	$storage = new BigTreeStorage;
	
	// If we're replacing an existing file, find out its name
	if (isset($_POST["replace"])) {
		$admin->requireLevel(1);
		$replacing = $admin->getResource($_POST["replace"]);
		$pinfo = BigTree::pathInfo($replacing["file"]);
		$replacing = $pinfo["basename"];
		// Set a recently replaced cookie so we don't use cached images
		setcookie('bigtree_admin[recently_replaced_file]',true,time()+300,str_replace(DOMAIN,"",WWW_ROOT));
	} else {
		$replacing = false;
	}

	$folder = isset($_POST["folder"]) ? sqlescape($_POST["folder"]) : false;
	$errors = array();
	$successes = 0;

	// This is an iFrame, so we're going to call the parent from it.
	echo '<html><body><script>';

	// If the user doesn't have permission to upload to this folder, throw an error.
	$perm = $admin->getResourceFolderPermission($folder);
	if ($perm != "p") {
		echo 'parent.BigTreeFileManager.uploadError("You do not have permission to upload to this folder.");';
	} else {
		foreach ($_FILES["files"]["tmp_name"] as $number => $temp_name) {
			$error = $_FILES["files"]["error"][$number];
			$file_name = $replacing ? $replacing : $_FILES["files"]["name"][$number];

			// Throw a growl error
			if ($error) {
				$file_name = htmlspecialchars($file_name);
				if ($error == 2 || $error == 1) {
					$errors[] = $file_name." was too large ".BigTree::formatBytes(BigTree::uploadMaxFileSize())." max)";
				} else {
					$errors[] = "Uploading $file_name failed (unknown error)";
				}
			// File successfully uploaded
			} elseif ($temp_name) {
				// See if this file already exists
				if ($replacing || !$admin->matchResourceMD5($temp_name,$_POST["folder"])) {
					$md5 = md5_file($temp_name);
		
					// Get the name and file extension
					$n = strrev($file_name);
					$extension = strtolower(strrev(substr($n,0,strpos($n,"."))));
		
					// See if it's an image
					list($iwidth,$iheight,$itype,$iattr) = getimagesize($temp_name);
		
					// It's a regular file
					if ($itype != IMAGETYPE_GIF && $itype != IMAGETYPE_JPEG && $itype != IMAGETYPE_PNG) {
						$type = "file";
						if ($replacing) {
							$file = $storage->replace($temp_name,$file_name,"files/resources/");
						} else {
							$file = $storage->store($temp_name,$file_name,"files/resources/");
						}
						// If we failed, either cloud storage upload failed, directory permissions are bad, or the file type isn't permitted
						if (!$file) {
							if ($storage->DisabledFileError) {
								$errors[] = "$file_name has a disallowed extension: $extension.";
							} else {
								$errors[] = "Uploading $file_name failed (unknown error).";
							}
						// Otherwise make the database entry for the file we uplaoded.
						} else {
							if (!$replacing) {
								$admin->createResource($folder,$file,$md5,$file_name,$extension);
							}
						}
					// It's an image
					} else {
						$type = "image";
		
						// We're going to create a list view and detail view thumbnail plus whatever we're requesting to have through Settings
						$thumbnails_to_create = array(
							"bigtree_internal_list" => array("width" => 100, "height" => 100, "prefix" => "bigtree_list_thumb_"),
							"bigtree_internal_detail" => array("width" => 190, "height" => 145, "prefix" => "bigtree_detail_thumb_")
						);
						$more_thumb_types = $cms->getSetting("bigtree-file-manager-thumbnail-sizes");
						if (is_array($more_thumb_types)) {
							foreach ($more_thumb_types as $thumb) {
								$thumbnails_to_create[$thumb["title"]] = $thumb;
							}
						}
		
						// Do lots of image awesomesauce.
						$itype_exts = array(IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif");
						$first_copy = $temp_name;
		
						list($iwidth,$iheight,$itype,$iattr) = getimagesize($first_copy);
		
						foreach ($thumbnails_to_create as $thumb) {
							// We don't want to add multiple errors and we also don't want to waste effort getting thumbnail sizes if we already failed.
							if (!$error) {
								$sizes = BigTree::getThumbnailSizes($first_copy,$thumb["width"],$thumb["height"]);
								if (!BigTree::imageManipulationMemoryAvailable($first_copy,$sizes[3],$sizes[4],$iwidth,$iheight)) {
									$errors[] = "$file_name is too large for the server to manipulate. Please upload a smaller version of this image.";
									unlink($first_copy);
								}
							}
						}
		
						if (!$error) {
							// Now let's make the thumbnails we need for the image manager
							$thumbs = array();
							$pinfo = BigTree::pathInfo($file_name);
		
							// Create a bunch of thumbnails
							foreach ($thumbnails_to_create as $key => $thumb) {
								if ($iwidth > $thumb["width"] || $iheight > $thumb["height"]) {
									$temp_thumb = SITE_ROOT."files/".uniqid("temp-").$itype_exts[$itype];
									BigTree::createThumbnail($first_copy,$temp_thumb,$thumb["width"],$thumb["height"]);
		
									if ($replacing) {
										$file = $storage->replace($temp_thumb,$thumb["prefix"].$pinfo["basename"],"files/resources/");
									} else {
										$file = $storage->store($temp_thumb,$thumb["prefix"].$pinfo["basename"],"files/resources/");
									}
									$thumbs[$key] = $file;
								}
							}
		
							// Upload the original to the proper place.
							if ($replacing) {
								$file = $storage->replace($first_copy,$file_name,"files/resources/");
							} else {
								$file = $storage->store($first_copy,$file_name,"files/resources/");
							}
		
							if (!$file) {
								$errors[] = "Uploading ".htmlspecialchars($file_name)." failed (unknown error).";
							} else {
								if (!$replacing) {
									$admin->createResource($folder,$file,$md5,$file_name,$extension,"on",$iheight,$iwidth,$thumbs);
								} else {
									$admin->updateResource($_POST["replace"],array(
										"date" => date("Y-m-d H:i:s"),
										"md5" => $md5,
										"height" => $iheight,
										"width" => $iwidth,
										"thumbs" => BigTree::json($thumbs)
									));
								}
							}
						}
					}
				}
			}
		}	
	}

	if (count($errors)) {
		$uploaded = count($_FILES["files"]["tmp_name"]) - count($errors);
		$success_message = "$uploaded file".($uploaded != 1 ? "s" : "")." uploaded successfully.";
		echo 'parent.BigTreeFileManager.uploadError("'.implode("<br />",$errors).'","'.$success_message.'");</script></body></html>';
	} else {
		echo 'parent.BigTreeFileManager.finishedUpload('.json_encode($errors).');</script></body></html>';
	}
?>
