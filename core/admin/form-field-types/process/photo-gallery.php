<?php
	namespace BigTree;
	
	/**
	 * @global Field $this
	 */
	
	$photo_gallery = array();
	
	if (is_array($this->Input)) {
		foreach ($this->Input as $photo_count => $data) {
			// Existing Data
			if ($data["image"]) {
				$data["caption"] = Text::htmlEncode($data["caption"]);
				$photo_gallery[] = $data;
			// Uploaded File
			} elseif ($this->FileInput[$photo_count]["image"]["name"]) {
				$field_copy = clone $this;
				$field_copy->FileInput = $this->FileInput[$photo_count]["image"];
				$file = $field_copy->processImageUpload();
				
				if ($file) {
					$photo_gallery[] = array("caption" => Text::htmlEncode($data["caption"]), "image" => $file);
				}
			// File From Image Manager
			} elseif ($data["existing"]) {
				$data["existing"] = str_replace(WWW_ROOT, SITE_ROOT, $data["existing"]);
				$pinfo = pathinfo($data["existing"]);
				$tmp_name = SITE_ROOT."files/".uniqid("temp-").".img";
				
				FileSystem::copyFile($data["existing"], $tmp_name);
				
				$field_copy = clone $this;
				$field_copy->FileInput = array(
					"name" => $pinfo["basename"],
					"tmp_name" => $tmp_name,
					"error" => false
				);
				$file = $field_copy->processImageUpload();
				
				if ($file) {
					$photo_gallery[] = array("caption" => Text::htmlEncode($data["caption"]), "image" => $file);
				}
			}
		}
	}
	
	$this->Output = $photo_gallery;
	
	