<?php
	namespace BigTree;
	
	/**
	 * @global $this Field
	 */
	
	if (!is_array($this->Input)) {
		$this->Input = [];
	}
	
	$this->Output = [];
	
	// Make sure file-only entries are represented
	if (is_array($this->FileInput)) {
		foreach ($this->FileInput as $index => $data) {
			if (!isset($this->Input[$index])) {
				$this->Input[$index] = [];
			}
		}
	}
	
	foreach ($this->Input as $index => $data) {
		// Make sure something has been entered
		if (!array_filter((array) $data) && !array_filter((array) $this->FileInput[$index])) {
			continue;
		}
		
		$entry = [];
		
		// Process a manual video upload
		if ($data["info"]["*localvideo"] || $this->FileInput[$index]["info"]["*localvideo"]["tmp_name"]) {
			// Process the uploaded video
			$sub_field = new Field([
				"title" => "Video",
				"key" => "*localvideo",
				"type" => "upload",
				"input" => $data["info"]["*localvideo"],
				"file_input" => $this->FileInput[$index]["info"]["*localvideo"],
				"settings" => $this->Settings
			]);
			$output = $sub_field->process();
			
			// If this field fails, we shouldn't upload the image
			if ($output) {
				$entry["type"] = "video";
				$entry["video"] = [
					"service" => "local",
					"url" => $output
				];
				
				// Process the cover image
				$image_field = new Field([
					"title" => "Photo",
					"key" => "*photo",
					"type" => "image",
					"input" => $data["info"]["*photo"],
					"file_input" => $this->FileInput[$index]["info"]["*photo"],
					"settings" => $this->Settings
				]);
				$output = $image_field->process();
				
				if ($output) {
					$entry["image"] = $output;
				}
			}
			
		// Process a photo upload
		} elseif ($data["info"]["*photo"] || $this->FileInput[$index]["info"]["*photo"]["tmp_name"]) {
			$sub_field = new Field([
				"title" => "Photo",
				"key" => "*photo",
				"type" => "image",
				"input" => $data["info"]["*photo"],
				"file_input" => $this->FileInput[$index]["info"]["*photo"],
				"settings" => $this->Settings
			]);
			$output = $sub_field->process();
			
			if ($output) {
				$entry["type"] = "photo";
				$entry["image"] = $output;
			}
			
		// Process a video
		} elseif ($data["info"]["*video"]) {
			$sub_field = new Field([
				"title" => "Video URL",
				"key" => "*video",
				"type" => "video",
				"input" => $data["info"]["*video"],
				"file_input" => $this->FileInput[$index]["info"]["*video"],
				"settings" => $this->Settings
			]);
			$output = $sub_field->process();
			
			if ($output) {
				$entry["type"] = "video";
				$entry["image"] = $output["image"];
				unset($output["image"]);
				$entry["video"] = $output;
			}
			
		// Existing unchanged field
		} elseif ($data["type"]) {
			$entry = $data;
		}
		
		// Only run the rest if we successfully processed a video or photo
		if (!array_filter((array) $entry)) {
			continue;
		}
		
		// Handle all the additional columns
		foreach (array_filter((array) $this->Settings["columns"]) as $resource) {
			// Sanitize field settings
			$settings = @json_decode($resource["settings"], true);			
			$settings = is_array($settings) ? $settings : [];
			
			if (empty($settings["directory"])) {
				$settings["directory"] = "files/pages/";
			}
			
			// Sanitize user input
			$input = $data["info"][$resource["id"]];
			
			if (is_string($input) && is_array(json_decode($input, true))) {
				$input = json_decode($input, true);
			}
			
			$sub_field = new Field([
				"type" => $resource["type"],
				"title" => $resource["title"],
				"key" => $resource["id"],
				"settings" => $settings,
				"input" => $input,
				"file_input" => $this->FileInput[$index]["info"][$resource["id"]]
			]);
			$output = $sub_field->process();
			
			if (!is_null($output)) {
				$entry["info"][$resource["id"]] = $output;
			}
		}
		
		$this->Output[] = $entry;
	}
	
	foreach ($this->Output as $index => $entry) {
		if (!empty($entry["info"]["caption"])) {
			$this->Output[$index]["caption"] = Text::htmlEncode(strip_tags($entry["info"]["caption"]));
		}
	}
