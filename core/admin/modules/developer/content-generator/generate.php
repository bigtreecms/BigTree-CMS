<?php
	namespace BigTree;

	$random_image = function($width, $height, $file) {
		$services = array(
			"http://lorempixel.com/width/height",
			"https://placeimg.com/width/height/any",
			"http://www.fillmurray.com/width/height",
			"https://placekitten.com/width/height"
		);
		$data = false;
		while (!$data) {
			$url = str_replace(array("width", "height"), array($width, $height), $services[array_rand($services)]);
			$data = cURL::request($url);
		}
		file_put_contents($file, $data);
		chmod($file, 0777);
	};

	$generate_image = function($options) use ($random_image) {
		if (!file_exists(SITE_ROOT."files/temp/")) {
			mkdir(SITE_ROOT."files/temp/");
		}

		$file_name = SITE_ROOT."files/temp/".uniqid("temp-").".jpg";
		while (file_exists($file_name)) {
			$file_name = SITE_ROOT."files/temp/".uniqid("temp-", true).".jpg";
		}

		// Some image services might be down, so we keep trying til we get one
		$created_image_width = false;
		while (!$created_image_width) {
			if ($options["min_width"] && $options["min_height"]) {
				$random_image($options["min_width"], $options["min_height"], $file_name);
			} else {
				$random_image(1280, 800, $file_name);
			}

			list($created_image_width) = getimagesize($file_name);
		}

		foreach (array_filter((array) $options["crops"]) as $crop) {
			$crop_file = FileSystem::getPrefixedFile($file_name, $crop["prefix"]);
			Image::centerCrop($file_name, $crop_file, $crop["width"], $crop["height"]);

			foreach (array_filter((array) $crop["thumbs"]) as $thumb) {
				Image::createThumbnail($crop_file, FileSystem::getPrefixedFile($file_name, $thumb["prefix"]), $thumb["width"], $thumb["height"]);
			}
		}

		foreach (array_filter((array) $options["thumbs"]) as $thumb) {
			Image::createThumbnail($file_name, FileSystem::getPrefixedFile($file_name, $thumb["prefix"]), $thumb["width"], $thumb["height"]);
		}

		foreach (array_filter((array) $options["center_crops"]) as $crop) {
			Image::centerCrop($file_name, FileSystem::getPrefixedFile($file_name, $crop["prefix"]), $crop["width"], $crop["height"]);
		}

		return $file_name;
	};

	$generate_data = function($field) use ($generate_image) {
		$type = $field["type"];
		$options = $field["settings"];

		if ($type == "text") {
			return ucwords(LoremIpsum::getWords(rand(6, 10)));
		} elseif ($type == "textarea") {
			return LoremIpsum::getSentences(rand(3, 6));
		} elseif ($type == "html") {
			return LoremIpsum::getParagraphs(rand(2, 6), "p");
		} elseif ($type == "upload") {
			if ($options["image"]) {
				return str_replace(SITE_ROOT, "{wwwroot}", $generate_image($options));
			}
		} elseif ($type == "list") {
			if ($options["list_type"] == "static") {
				return $options["list"][array_rand($options["list"])]["value"];
			} elseif ($options["list_type"] == "state") {
				return Field::$StateList[array_rand(Field::$StateList)];
			} elseif ($options["list_type"] == "country") {
				return Field::$CountryList[array_rand(Field::$CountryList)];
			} else {
				return SQL::fetchSingle("SELECT `id` FROM `".$options["pop-table"]."` ORDER BY RAND() LIMIT 1");
			}
		} elseif ($type == "checkbox") {
			if (rand(0, 1) == 1) {
				return "on";
			}
		} elseif ($type == "date") {
			$offset = rand(-365, 365);
			if ($offset < 0) {
				return date("Y-m-d", strtotime("$offset days"));
			} else {
				return date("Y-m-d", strtotime("+$offset days"));
			}
		} elseif ($type == "time") {
			return str_pad(rand(0, 23), 2, "0", STR_PAD_LEFT).":".str_pad(rand(0, 59), 2, "0", STR_PAD_LEFT);
		} elseif ($type == "datetime") {
			$time = str_pad(rand(0, 23), 2, "0", STR_PAD_LEFT).":".str_pad(rand(0, 59), 2, "0", STR_PAD_LEFT);
			$offset = rand(-365, 365);
			if ($offset < 0) {
				return date("Y-m-d", strtotime("$offset days"))." ".$time;
			} else {
				return date("Y-m-d", strtotime("+$offset days"))." ".$time;
			}
		} elseif ($type == "photo-gallery") {
			$count = rand(3, 9);
			$data = [];
			while ($count) {
				$data[] = array("caption" => LoremIpsum::getWords(rand(6, 10)), "image" => str_replace(SITE_ROOT, "{wwwroot}", $generate_image($options)));
				$count--;
			}

			return $data;
		} elseif ($type == "route") {
			global $data, $form;

			$route = Link::urlify(strip_tags($data[$options["source"]]));

			return SQL::unique($form["table"], $field["key"], $route);
		} elseif ($type == "many-to-many") {
			global $many_to_many;

			$total = SQL::fetchSingle("SELECT COUNT(*) FROM `".$options["mtm-other-table"]."`");
			$number_to_make = rand(1, $total);
			$used = [];

			while ($number_to_make) {
				$random_id = SQL::fetchSingle("SELECT id FROM `".$options["mtm-other-table"]."` ORDER BY RAND() LIMIT 1");

				if (!in_array($random_id, $used)) {
					$many_to_many[] = array(
						"table" => $options["mtm-connecting-table"],
						"my_field" => $options["mtm-my-id"],
						"other_field" => $options["mtm-other-id"],
						"value" => $random_id
					);
					$used[] = $random_id;
				}

				$number_to_make--;
			}
		}

		return "";
	};

	// Generator code
	$form = new ModuleForm($_POST["form"]);
	$count = $total = intval($_POST["count"]);

	if (!$count) {
		$count = 25;
	}

	// Loop until we're done generating
	while ($count) {
		$many_to_many = [];
		$data = [];

		foreach ($form->Fields as $field) {
			$value = $generate_data($field);

			if ($value) {
				$data[$field["column"]] = $value;
			}
		}

		$form->createEntry($data, $many_to_many);
		$count--;
	}

	Utils::growl("Content Generator", "Generated Test Entries");
	Router::redirect(DEVELOPER_ROOT."test-content/");
	