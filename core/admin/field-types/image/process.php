<?php
	namespace BigTree;
	
	/**
	 * @global $post_data
	 */
	
	// If a file upload error occurred, return the old data and set errors
	if ($this->FileInput["error"] == 1 || $this->FileInput["error"] == 2) {
		Router::logUserError(
			"The image you uploaded (:image:) was too large &mdash; <strong>Max file size: :max:</strong>",
			$this->Title,
			[":image:" => $this->FileInput["name"], ":max:" => ini_get("upload_max_filesize")]
		);
		$this->Output = $this->Input;
	} elseif ($this->FileInput["error"] == 3) {
		Router::logUserError("The image upload failed (:image:).", $this->Title, [":image:" => $this->FileInput["name"]]);
		$this->Output = $this->Input;
	} else {
		// We uploaded a new image.
		if (is_uploaded_file($this->FileInput["tmp_name"])) {
			$file = $this->processImageUpload();
			$this->Output = $file ? $file : $this->Input;
		// Using an existing image or one from the Image Browser
		} else {
			$this->Output = $this->Input;
			
			// We're trying to use an image from the Image Browser.
			if (substr($this->Output, 0, 11) == "resource://") {
				// It's technically a new file now, but we pulled it from resources so we might need to crop it.
				$resource = Resource::getByFile(substr($this->Output, 11));
				$resource_location = str_replace(["{wwwroot}", WWW_ROOT, "{staticroot}", STATIC_ROOT], SITE_ROOT, $resource["file"]);
				
				// See if the file was actually stored in the cloud
				if (!file_exists($resource_location)) {
					$resource_location = $resource["file"];
				}
				
				$pinfo = pathinfo($resource_location);
				
				// Emulate a newly uploaded file
				$this->FileInput = [
					"name" => $pinfo["basename"],
					"tmp_name" => SITE_ROOT."files/".uniqid("temp-").".img",
					"error" => false
				];
				FileSystem::copyFile($resource_location, $this->FileInput["tmp_name"]);
				
				$file = $this->processImageUpload();
				$this->Output = $file ? $file : $this->Input;
			} elseif (!empty($this->POSTData["__".$this->Key."_recrop__"])) {
				// User has asked for a re-crop
				$image = new Image(str_replace(STATIC_ROOT, SITE_ROOT, $this->Input), $this->Settings, true);
				$image_copy = $image->copy();
				$image_copy->StoredName = pathinfo($this->Input, PATHINFO_BASENAME);
				$image_copy->filterGeneratableCrops();
				$crops = $image_copy->processCrops();
				
				if (!count($crops)) {
					$image_copy->destroy();
				}
				
				Field::$Crops = array_merge(Field::$Crops, $crops);
			}
		}
	}
