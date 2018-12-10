<?php
	$video = null;
	$url = $_POST["video"];
	$settings = BigTreeJSONDB::get("config", "media-settings");
	$preset = $settings["presets"]["default"];

	// YouTube
	if (strpos($url,"youtu.be") !== false || strpos($url,"youtube.com") !== false) {
		// Try to grab the ID from the YouTube URL (courtesy of various Stack Overflow authors)
		$pattern =
			'%^# Match any youtube URL
			(?:https?://)?  # Optional scheme. Either http or https
			(?:www\.)?	    # Optional www subdomain
			(?:			    # Group host alternatives
			  youtu\.be/	# Either youtu.be,
			| youtube\.com  # or youtube.com
			  (?:		    # Group path alternatives
				/embed/	    # Either /embed/
			  | /v/		    # or /v/
			  | .*v=		# or /watch\?v=
			  )			    # End path alternatives.
			)			    # End host alternatives.
			([\w-]{10,12})  # Allow 10-12 for 11 char youtube id.
			($|&).*		    # if additional parameters are also in query string after video id.
			$%x';
		$result = preg_match($pattern, $url, $matches);

		// No ID match? Bad URL.
		if ($result === false) {
			$admin->growl("Files", "Invalid URL", "error");
			BigTree::redirect($_SERVER["HTTP_REFERER"]."?error=The URL you entered is not a valid YouTube URL.");
		// Got our YouTube ID
		} else {
			$youtube = new BigTreeYouTubeAPI;
			$video_id = $matches[1];
			$oembed_data = json_decode(BigTree::cURL("https://www.youtube.com/oembed?url=".urlencode("https://youtube.com/watch?v=".$video_id)), true);

			if (empty($oembed_data["html"])) {
				$admin->growl("Files", "Invalid URL", "error");
				BigTree::redirect($_SERVER["HTTP_REFERER"]."?error=The URL you entered is not a valid YouTube video URL.");
			}

			$video = [
				"service" => "YouTube",
				"id" => $video_id,
				"title" => $oembed_data["title"],
				"description" => null,
				"image" => $oembed_data["thumbnail_url"],
				"url" => "https://youtube.com/watch?v=".$video_id,
				"user_id" => null,
				"user_name" => $oembed_data["author_name"],
				"user_url" => $oembed_data["author_url"],
				"upload_date" => null,
				"height" => null,
				"width" => null,
				"duration" => null,
				"embed" => $oembed_data["html"]
			];

			if ($youtube->Connected) {
				// Try a higher authenticated version that gets us file dimensions, must own the file
				try {
					$response = $youtube->callUncached("videos", [
						"part" => "id,snippet,contentDetails,player,statistics,status,topicDetails,recordingDetails,fileDetails",
						"id" => $video_id
					]);
	
					if (isset($response->items) && count($response->items)) {
						if (!empty($response->items[0]->fileDetails->videoStreams[0])) {
							$video["height"] = $response->items[0]->fileDetails->videoStreams[0]->heightPixels;
							$video["width"] = $response->items[0]->fileDetails->videoStreams[0]->widthPixels;
						}
					}
				} catch (Exception $e) {}

				// Now use the standard
				$video_data = $youtube->getVideo($video_id);

				// Try for max resolution first, then high, then default
				$source_image = $video_data->Images->Maxres ? $video_data->Images->Maxres : $video_data->Images->High;
				$source_image = $source_image ? $source_image : $video_data->Images->Default;

				$video["image"] = $source_image;
				$video["description"] = $video_data->Description;
				$video["user_id"] = $video_data->ChannelID;
				$video["upload_date"] = $video_data->Timestamp;
				$video["duration"] = ($video_data->Duration->Hours * 3600 + $video_data->Duration->Minutes * 60 + $video_data->Duration->Seconds);
			}
		}

	// Vimeo
	} elseif (strpos($url,"vimeo.com") !== false) {
		$url_pieces = explode("/",$url);
		$video_id = end($url_pieces);
		$json = json_decode(BigTree::cURL("http://vimeo.com/api/v2/video/$video_id.json"),true);

		// Good video
		if (array_filter((array)$json)) {
			// Try to get the largest source image available
			$source_image = $json[0]["thumbnail_large"];
			$source_image = $source_image ? $source_image : $json[0]["thumbnail_medium"];
			$source_image = $source_image ? $source_image : $json[0]["thumbnail_small"];

			$video = [
				"service" => "Vimeo",
				"id" => $video_id,
				"title" => $json[0]["title"],
				"description" => $json[0]["description"],
				"image" => $source_image,
				"url" => $json[0]["url"],
				"user_id" => $json[0]["user_id"],
				"user_name" => $json[0]["user_name"],
				"user_url" => $json[0]["user_url"],
				"upload_date" => $json[0]["upload_date"],
				"height" => $json[0]["height"],
				"width" => $json[0]["width"],
				"duration" => $json[0]["duration"],
				"embed" => '<iframe src="https://player.vimeo.com/video/'.$video_id.'?byline=0&portrait=0" width="'.$width.'" height="'.$height.'" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>'
			];
		// No video :(
		} else {
			$admin->growl("Files", "Invalid URL", "error");
			BigTree::redirect($_SERVER["HTTP_REFERER"]."?error=The URL you entered is not a valid Vimeo video URL.");
		}
	// Invalid URL
	} else {
		$admin->growl("Files", "Invalid URL", "error");
		BigTree::redirect($_SERVER["HTTP_REFERER"]."?error=The URL you entered is not a valid video service URL.");
	}

	$extension = strtolower(pathinfo($video["image"], PATHINFO_EXTENSION));
	$file_name = SITE_ROOT."files/temporary/".$admin->ID."/".$video["id"].".".$extension;
	BigTree::copyFile($video["image"], $file_name);

	$min_height = intval($preset["min_height"]);
	$min_width = intval($preset["min_width"]);

	list($width, $height, $type, $attr) = getimagesize($file_name);

	// Scale up content that doesn't meet minimums
	if ($width < $min_width || $height < $min_height) {
		$image = new BigTreeImage($file_name);
		$image->upscale(null, $min_width, $min_height);
	}

	$field = [
		"title" => $video["title"],
		"file_input" => [
			"tmp_name" => $file_name,
			"name" => $video["id"].".".$extension,
			"error" => 0
		],
		"settings" => [
			"directory" => "files/resources/",
			"preset" => "default"
		]
	];

	$video["image"] = $admin->processImageUpload($field);
	$resource_id = $admin->createResource($_POST["folder"], null, null, $type = "video", [], [], $video);
	$admin->growl("Files", "Created Video");

	$_SESSION["bigtree_admin"]["form_data"] = [
		"edit_link" => ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/",
		"return_link" => ADMIN_ROOT."files/edit/file/$resource_id/",
		"crop_key" => $cms->cacheUnique("org.bigtreecms.crops", $bigtree["crops"])
	];
	
	if (is_array($bigtree["crops"]) && count($bigtree["crops"])) {
		BigTree::redirect(ADMIN_ROOT."files/crop/".intval($bigtree["commands"][0])."/");
	} else {
		BigTree::redirect(ADMIN_ROOT."files/edit/file/$resource_id/");
	}