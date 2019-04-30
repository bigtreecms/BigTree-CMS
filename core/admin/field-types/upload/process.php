<?php
	namespace BigTree;
	
	/**
	 * @global $bigtree
	 */
	
	// If a file upload error occurred, return the old data and set errors
	if ($this->FileInput["error"] == 1 || $this->FileInput["error"] == 2) {
		$bigtree["errors"][] = [
			"field" => $this->Title,
			"error" => Text::translate("The file you uploaded (:file:) was too large &mdash; <strong>Max file size: :max:</strong>",
									   false, [":file:" => $this->FileInput["name"], ":max:" => ini_get("upload_max_filesize")])
		];
		$this->Output = $this->Input;
	} elseif ($this->FileInput["error"] == 3) {
		$bigtree["errors"][] = [
			"field" => $this->Title,
			"error" => Text::translate("The file upload failed (:file:).", false, [":file:" => $this->FileInput["name"]])
		];
		$this->Output = $this->Input;
	} else {
		if (is_uploaded_file($this->FileInput["tmp_name"])) {
			$storage = new Storage;
			$this->Output = $storage->store($this->FileInput["tmp_name"], $this->FileInput["name"], $this->Settings["directory"]);

			if (!$this->Output) {
				if ($storage->DisabledFileError) {
					$bigtree["errors"][] = [
						"field" => $this->Title, 
						"error" => Text::translate("Could not upload file. The file extension is not allowed.")
					];
				} else {
					$bigtree["errors"][] = [
						"field" => $this->Title, 
						"error" => Text::translate("Could not upload file. The destination is not writable.")
					];
				}
			}
		} else {
			$this->Output = $this->Input;
		}
	}
	