<?php
	use BigTree\FileSystem;

	class BigTreeTestContentLoremIpsum {
		public $words = array(
			// Lorem ipsum...
			'lorem',		'ipsum',	   'dolor',		'sit',
			'amet',		 'consectetur', 'adipiscing',   'elit',
			// The rest of the vocabulary
			'a',			'ac',		  'accumsan',	 'ad',
			'aenean',	   'aliquam',	 'aliquet',	  'ante',
			'aptent',	   'arcu',		'at',		   'auctor',
			'augue',		'bibendum',	'blandit',	  'class',
			'commodo',	  'condimentum', 'congue',	   'consequat',
			'conubia',	  'convallis',   'cras',		 'cubilia',
			'cum',		  'curabitur',   'curae',		'cursus',
			'dapibus',	  'diam',		'dictum',	   'dictumst',
			'dignissim',	'dis',		 'donec',		'dui',
			'duis',		 'egestas',	 'eget',		 'eleifend',
			'elementum',	'enim',		'erat',		 'eros',
			'est',		  'et',		  'etiam',		'eu',
			'euismod',	  'facilisi',	'facilisis',	'fames',
			'faucibus',	 'felis',	   'fermentum',	'feugiat',
			'fringilla',	'fusce',	   'gravida',	  'habitant',
			'habitasse',	'hac',		 'hendrerit',	'himenaeos',
			'iaculis',	  'id',		  'imperdiet',	'in',
			'inceptos',	 'integer',	 'interdum',	 'justo',
			'lacinia',	  'lacus',	   'laoreet',	  'lectus',
			'leo',		  'libero',	  'ligula',	   'litora',
			'lobortis',	 'luctus',	  'maecenas',	 'magna',
			'magnis',	   'malesuada',   'massa',		'mattis',
			'mauris',	   'metus',	   'mi',		   'molestie',
			'mollis',	   'montes',	  'morbi',		'mus',
			'nam',		  'nascetur',	'natoque',	  'nec',
			'neque',		'netus',	   'nibh',		 'nisi',
			'nisl',		 'non',		 'nostra',	   'nulla',
			'nullam',	   'nunc',		'odio',		 'orci',
			'ornare',	   'parturient',  'pellentesque', 'penatibus',
			'per',		  'pharetra',	'phasellus',	'placerat',
			'platea',	   'porta',	   'porttitor',	'posuere',
			'potenti',	  'praesent',	'pretium',	  'primis',
			'proin',		'pulvinar',	'purus',		'quam',
			'quis',		 'quisque',	 'rhoncus',	  'ridiculus',
			'risus',		'rutrum',	  'sagittis',	 'sapien',
			'scelerisque',  'sed',		 'sem',		  'semper',
			'senectus',	 'sociis',	  'sociosqu',	 'sodales',
			'sollicitudin', 'suscipit',	'suspendisse',  'taciti',
			'tellus',	   'tempor',	  'tempus',	   'tincidunt',
			'torquent',	 'tortor',	  'tristique',	'turpis',
			'ullamcorper',  'ultrices',	'ultricies',	'urna',
			'ut',		   'varius',	  'vehicula',	 'vel',
			'velit',		'venenatis',   'vestibulum',   'vitae',
			'vivamus',	  'viverra',	 'volutpat',	 'vulputate',
		);
		
		function words($count = 1, $tags = false, $array = false) {
			$words = array();
			$word_count = 0;
	
			while ($word_count < $count) {
				$shuffle = true;
	
				while ($shuffle) {
					shuffle($this->words);
					if (!$word_count || $words[$word_count - 1] != $this->words[0]) {
						$words = array_merge($words, $this->words);
						$word_count = count($words);
						$shuffle = false;
					}
				}
			}
	
			$words = array_slice($words, 0, $count);	
			return $this->output($words, $tags, $array);
		}

		function sentences($count = 1, $tags = false, $array = false) {
			$sentences = array();
	
			for ($i = 0; $i < $count; $i++) {
				$sentences[] = $this->words($this->gauss(24.46, 5.08),false,true);
			}
	
			$this->punctuate($sentences);
	
			return $this->output($sentences, $tags, $array);
		}

		function paragraphs($count = 1, $tags = false, $array = false) {
			$paragraphs = array();

			for ($i = 0; $i < $count; $i++) {
				$paragraphs[] = $this->sentences($this->gauss(5.8, 1.93));
			}

			return $this->output($paragraphs, $tags, $array, "\n\n");
		}

		private function gauss($mean, $std_dev) {
			$x = mt_rand() / mt_getrandmax();
			$y = mt_rand() / mt_getrandmax();
			$z = sqrt(-2 * log($x)) * cos(2 * pi() * $y);
			return $z * $std_dev + $mean;
		}

		private function punctuate(&$sentences) {
			foreach ($sentences as $key => $sentence) {
				$words = count($sentence);	
				if ($words > 4) {
					$mean	= log($words, 6);
					$std_dev = $mean / 6;
					$commas  = round($this->gauss($mean, $std_dev));
	
					for ($i = 1; $i <= $commas; $i++) {
						$word = round($i * $words / ($commas + 1));
	
						if ($word < ($words - 1) && $word > 0) {
							$sentence[$word] .= ',';
						}
					}
				}
	
				$sentences[$key] = ucfirst(implode(' ', $sentence) . '.');
			}
		}
	
		private function output($strings, $tags, $array, $delimiter = ' ') {
			if ($tags) {
				if (!is_array($tags)) {
					$tags = array($tags);
				} else {
					$tags = array_reverse($tags);
				}
	
				foreach ($strings as $key => $string) {
					foreach ($tags as $tag) {
						if ($tag[0] == '<') {
							$string = str_replace('$1', $string, $tag);
						} else {
							$string = sprintf('<%1$s>%2$s</%1$s>', $tag, $string);
						}
	
						$strings[$key] = $string;
					}
				}
			}
	
			if (!$array) {
				$strings = implode($delimiter, $strings);
			}
	
			return $strings;
		}
	}

	$random_image = function($width,$height,$file) {
		$services = array(
			"http://lorempixel.com/width/height",
			"https://placeimg.com/width/height/any",
			"http://www.fillmurray.com/width/height",
			"https://placekitten.com/width/height"
		);
		$data = false;
		while (!$data) {
			$url = str_replace(array("width","height"),array($width,$height),$services[array_rand($services)]);
			$data = BigTree::cURL($url);
		}
		file_put_contents($file,$data);
		chmod($file,0777);
	};

	$generate_image = function($options) {
		global $random_image;

		if (!file_exists(SITE_ROOT."files/temp/")) {
			mkdir(SITE_ROOT."files/temp/");
		}

		$file_name = SITE_ROOT."files/temp/".uniqid("temp-").".jpg";
		while (file_exists($file_name)) {
			$file_name = SITE_ROOT."files/temp/".uniqid("temp-",true).".jpg";
		}

		// Some image services might be down, so we keep trying til we get one
		$created_image_width = false;
		while (!$created_image_width) {
			if ($options["min_width"] && $options["min_height"]) {
				$random_image($options["min_width"],$options["min_height"],$file_name);
			} else {
				$random_image(1280,800,$file_name);
			}
			list($created_image_width) = getimagesize($file_name);
		}

		foreach (array_filter((array)$options["crops"]) as $crop) {
			$crop_file = FileSystem::getPrefixedFile($file_name,$crop["prefix"]);
			BigTree::centerCrop($file_name,$crop_file,$crop["width"],$crop["height"]);
			foreach (array_filter((array)$crop["thumbs"]) as $thumb) {
				BigTree::createThumbnail($crop_file,FileSystem::getPrefixedFile($file_name,$thumb["prefix"]),$thumb["width"],$thumb["height"]);	
			}
		}

		foreach (array_filter((array)$options["thumbs"]) as $thumb) {
			BigTree::createThumbnail($file_name,FileSystem::getPrefixedFile($file_name,$thumb["prefix"]),$thumb["width"],$thumb["height"]);
		}
				
		foreach (array_filter((array)$options["center_crops"]) as $crop) {
			BigTree::centerCrop($file_name,FileSystem::getPrefixedFile($file_name,$crop["prefix"]),$crop["width"],$crop["height"]);
		}

		return $file_name;
	};

	$generate_data = function($type,$options) {
		global $db, $generate_image;

		$lipsum = new BigTreeTestContentLoremIpsum;  
		if ($type == "text") {  
			return ucwords($lipsum->words(rand(6,10)));
		} elseif ($type == "textarea") {
			return $lipsum->sentences(rand(3,6));
		} elseif ($type == "html") {
			return $lipsum->paragraphs(rand(2,6),"p");
		} elseif ($type == "upload") {
			if ($options["image"]) {
				return str_replace(SITE_ROOT,"{wwwroot}",$generate_image($options));
			}
		} elseif ($type == "list") {
			if ($options["list_type"] == "static") {
				return $options["list"][array_rand($options["list"])]["value"];
			} elseif ($options["list_type"] == "state") {
				return BigTree::$StateList[array_rand(BigTree::$StateList)];
			} elseif ($options["list_type"] == "country") {
				return BigTree::$CountryList[array_rand(BigTree::$CountryList)];
			} else {
				return $db->fetchSingle("SELECT `id` FROM `".$options["pop-table"]."` ORDER BY RAND() LIMIT 1");
			}
		} elseif ($type == "checkbox") {
			if (rand(0,1) == 1) {
				return "on";
			}
		} elseif ($type == "date") {
			$offset = rand(-365,365);
			if ($offset < 0) {
				return date("Y-m-d",strtotime("$offset days"));
			} else {
				return date("Y-m-d",strtotime("+$offset days"));
			}
		} elseif ($type == "time") {
			return str_pad(rand(0,23),2,"0",STR_PAD_LEFT).":".str_pad(rand(0,59),2,"0",STR_PAD_LEFT);
		} elseif ($type == "datetime") {
			$time = str_pad(rand(0,23),2,"0",STR_PAD_LEFT).":".str_pad(rand(0,59),2,"0",STR_PAD_LEFT);
			$offset = rand(-365,365);
			if ($offset < 0) {
				return date("Y-m-d",strtotime("$offset days"))." ".$time;
			} else {
				return date("Y-m-d",strtotime("+$offset days"))." ".$time;
			}
		} elseif ($type == "photo-gallery") {
			$count = rand(3,9);
			$data = array();
			while ($count) {
				$data[] = array("caption" => $lipsum->words(rand(6,10)),"image" => str_replace(SITE_ROOT,"{wwwroot}",$generate_image($options)));
				$count--;
			}
			return $data;
		} elseif ($type == "route") {
			global $data,$form;

			$route = BigTreeCMS::urlify(strip_tags($data[$options["source"]]));
			return $db->unique($form["table"],$field["key"],$route);
		} elseif ($type == "many-to-many") {
			global $many_to_many;
			$total = $db->fetchSingle("SELECT COUNT(*) FROM `".$options["mtm-other-table"]."`");
			$number_to_make = rand(1,$total);
			$used = array();
			while ($number_to_make) {
				$random_id = $db->fetchSingle("SELECT id FROM `".$options["mtm-other-table"]."` ORDER BY RAND() LIMIT 1");
				if (!in_array($random_id,$used)) {
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
	$form = BigTreeAutoModule::getForm($_POST["form"]);
	$count = $total = intval($_POST["count"]);
	if (!$count) {
		$count = 25;
	}

	// Loop until we're done generating
	while ($count) {
		$many_to_many = array();
		$data = array();
		foreach ($form["fields"] as $field) {
			$value = $generate_data($field["type"],$field["options"]);
			if ($value) {
				$data[$field["column"]] = $value;
			}
		}
		$id = BigTreeAutoModule::createItem($form["table"],$data);
		foreach ($many_to_many as $mtm) {
			$db->insert($mtm["table"],array(
				$mtm["my_field"] => $id,
				$mtm["other_field"] => $mtm["value"]
			));
		}
		$count--;
	}

	$admin->growl("Content Generator","Generated $total Entries");
	BigTree::redirect(DEVELOPER_ROOT."test-content/");