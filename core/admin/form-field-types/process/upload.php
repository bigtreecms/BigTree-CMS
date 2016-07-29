<?php
	namespace BigTree;
	
	// If a file upload error occurred, return the old data and set errors
	if ($this->FileInput["error"] == 1 || $this->FileInput["error"] == 2) {
		$bigtree["errors"][] = array("field" => $this->Title, "error" => "The file you uploaded (".$this->FileInput["name"].") was too large &mdash; <strong>Max file size: ".ini_get("upload_max_filesize")."</strong>");
		$this->Output = $this->Input;
	} elseif ($this->FileInput["error"] == 3) {
		$bigtree["errors"][] = array("field" => $this->Title, "error" => "The file upload failed (".$this->FileInput["name"].").");
		$this->Output = $this->Input;
	} else {
		// We're processing a file.
		if (!$this->Settings["image"]) {
			if (is_uploaded_file($this->FileInput["tmp_name"])) {
				$storage = new Storage;
				$this->Output = $storage->store($this->FileInput["tmp_name"], $this->FileInput["name"], $this->Settings["directory"]);
				
				if (!$this->Output) {
					if ($storage->DisabledFileError) {
						$bigtree["errors"][] = array("field" => $this->Title, "error" => "Could not upload file. The file extension is not allowed.");
					} else {
						$bigtree["errors"][] = array("field" => $this->Title, "error" => "Could not upload file. The destination is not writable.");
					}
				}
			} else {
				$this->Output = $this->Input;
			}
			// We're processing an image.
		} else {
			// We uploaded a new image.
			if (is_uploaded_file($this->FileInput["tmp_name"])) {
				$file = $this->processImageUpload();
				$this->Output = $file ?: $this->Input;
			// Using an existing image or one from the Image Browser
			} else {
				$this->Output = $this->Input;
				
				// We're trying to use an image from the Image Browser.
				if (substr($this->Output, 0, 11) == "resource://") {
					// It's technically a new file now, but we pulled it from resources so we might need to crop it.
					$resource = Resource::getByFile(substr($this->Output, 11));
					$resource_location = str_replace(array("{wwwroot}", WWW_ROOT, "{staticroot}", STATIC_ROOT), SITE_ROOT, $resource->File);
					
					// See if the file was actually stored in the cloud
					if (!file_exists($resource_location)) {
						$resource_location = $resource->File;
					}
					
					$pinfo = pathinfo($resource_location);
					
					// Emulate a newly uploaded file
					$this->FileInput = array("name" => $pinfo["basename"], "tmp_name" => SITE_ROOT."files/".uniqid("temp-").".img", "error" => false);
					FileSystem::copyFile($resource_location, $this->FileInput["tmp_name"]);
					
					$file = $this->processImageUpload();
					$this->Output = $file ? $file : $this->Input;
				}
			}
		}
	}
	