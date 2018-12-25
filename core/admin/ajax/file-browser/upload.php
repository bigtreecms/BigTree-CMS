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
		$force_local_replace = ($replacing["location"] == "local");
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
							$file = $storage->replace($temp_name, $file_name, "files/resources/", true, $force_local_replace);
						} else {
							$file = $storage->store($temp_name, $file_name, "files/resources/");
						}
						
						// If we failed, either cloud storage upload failed, directory permissions are bad, or the file type isn't permitted
						if (!$file) {
							if ($storage->DisabledFileError) {
								$errors[] = htmlspecialchars($file_name)." has a disallowed extension: $extension.";
							} else {
								$errors[] = "Uploading ".htmlspecialchars($file_name)." failed (unknown error).";
							}
						// Otherwise make the database entry for the file we uplaoded.
						} else {
							if (!$replacing) {
								$admin->createResource($folder,$file,$md5,$file_name,$extension);
							} else {
								$admin->updateResource($_POST["replace"], array("date" => date("Y-m-d H:i:s")));
							}
						}
					// It's an image
					} else {
						$type = "image";
						$field = array(
							"file_input" => array(
								"tmp_name" => $_FILES["files"]["tmp_name"][$number],
								"name" => $replacing ?: $_FILES["files"]["name"][$number],
								"error" => $_FILES["files"]["error"][$number]
							),
							"options" => array(
								"directory" => "files/resources/",
								"thumbs" => array(
									array("width" => 100, "height" => 100, "prefix" => "bigtree_list_thumb_", "title" => "bigtree_internal_list"),
									array("width" => 190, "height" => 145, "prefix" => "bigtree_detail_thumb_", "title" => "bigtree_internal_detail")
								)
							)
						);
						$more_thumb_types = $cms->getSetting("bigtree-file-manager-thumbnail-sizes");
						
						if (is_array($more_thumb_types)) {
							foreach ($more_thumb_types as $thumb) {
								$field["options"]["thumbs"][] = $thumb;
							}
						}
		
						$file = $admin->processImageUpload($field, $replacing, $force_local_replace);
		
						if ($file) {
							$thumbs = array();

							foreach ($field["options"]["thumbs"] as $thumb) {
								$thumbs[$thumb["title"]] = BigTree::prefixFile($file, $thumb["prefix"]);
							}

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
						} else {
							$last_error = array_pop($bigtree["errors"]);
							$errors[] = BigTree::safeEncode($last_error["error"]);
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