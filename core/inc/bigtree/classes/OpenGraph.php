<?php
	/*
		Class: BigTree\OpenGraph
			Provides an interface for handling BigTree Open Graph data.
	*/
	
	namespace BigTree;
	use Exception;
	
	class OpenGraph
	{
		
		public static $Context = [];
		public static $Types = [
			"website",
			"article",
			"book",
			"business.business",
			"fitness.course",
			"game.achievement",
			"music.album",
			"music.playlist",
			"music.radio_station",
			"music.song",
			"place",
			"product",
			"profile",
			"restaurant.menu",
			"restaurant.menu_item",
			"restaurant.menu_section",
			"restaurant.restaurant",
			"video.episode",
			"video.movie",
			"video.other",
			"video.tv_show"
		];
		
		/*
			Function: drawHeadTags
				Draws the <title>, meta description, and open graph tags for the given context.
				The context defaults to the current page and can be changed via BigTreeCMS::setHeadContext

			Parameters:
				site_title - A site title that draws after the page title if entered, also used for og:site_name
				divider - The divider between the page title and site title, defaults to |
				fallback_image - A fallback image for when no OG image exists (defaults to null)
		*/
		
		public static function drawHeadTags(string $site_title = "", string $divider = "|",
											?string $fallback_image = null): void
		{
			/** @var Page $current_page */
			$current_page = Router::$CurrentPage;
			$context = static::$Context;
			$og = static::getData("bigtree_pages", $current_page->ID);
			$image_width = null;
			$image_height = null;
			
			if (empty($context)) {
				$title = $current_page->Title;
				$og_title = !empty($og["title"]) ? $og["title"] : $current_page->Title;
				$description = !empty($current_page->MetaDescription) ? $current_page->MetaDescription : $og["description"];
				$og_description = !empty($og["description"]) ? $og["description"] : $current_page->MetaDescription;
				$image = $og["image"] ?: $fallback_image;
				$image_width = $og["image_width"];
				$image_height = $og["image_height"];
				$type = $og["type"] ?: "website";
			} else {
				$context_og = static::getData($context["table"], $context["entry"]) ?: $og;
				
				if (!empty($context_og["title"])) {
					$title = $context_og["title"];
				} elseif (!empty($context["title"])) {
					$title = $context["title"];
				} else {
					$title = $og["title"] ?: $current_page->Title;
				}
				
				$og_title = $title;
				
				if (!empty($context_og["description"])) {
					$description = $context_og["description"];
				} elseif (!empty($context["description"])) {
					$description = $context["description"];
				} else {
					$description = $og["description"] ?: $current_page->MetaDescription;
				}
				
				$og_description = $description;
				
				if (!empty($context_og["type"])) {
					$type = $context_og["type"];
				} elseif (!empty($context["type"])) {
					$type = $context["type"];
				} else {
					$type = $og["type"] ?: "website";
				}
				
				if (!empty($context_og["image"])) {
					$image = $context_og["image"];
					$image_width = $context_og["image_width"];
					$image_height = $context_og["image_height"];
				} elseif (!empty($context["image"])) {
					$image = $context["image"];
				} else {
					$image = $og["image"] ?: $fallback_image;
					$image_width = $og["image_width"];
					$image_height = $og["image_height"];
				}
			}
			
			if (empty($title) && defined("BIGTREE_URL_IS_404")) {
				$title = "404";
			}
			
			if ($site_title && (defined("BIGTREE_URL_IS_404") || !empty($current_page->ID))) {
				$title .= Text::htmlEncode(" $divider $site_title");
			}
			
			echo "<title>$title</title>\n";
			echo '		<meta name="description" content="'.$description.'" />'."\n";
			echo '		<meta property="og:title" content="'.$og_title.'" />'."\n";
			echo '		<meta property="og:description" content="'.$og_description.'" />'."\n";
			echo '		<meta property="og:type" content="'.$type.'" />'."\n";
			
			if ($site_title) {
				echo '		<meta property="og:site_name" content="'.Text::htmlEncode($site_title).'" />'."\n";
			}
			
			if ($image) {
				if (substr($image, 0, 2) == "//") {
					$image = "https:".$image;
				}

				echo '		<meta property="og:image" content="'.$image.'" />'."\n";
				
				// Only get image dimensions if the image is local
				if (!$image_width && strpos($image, WWW_ROOT) === 0) {
					list($image_width, $image_height) = getimagesize(str_replace(WWW_ROOT, SITE_ROOT, $image));
				}
				
				if ($image_width && $image_height) {
					echo '		<meta property="og:image:width" content="'.intval($image_width).'" />'."\n";
					echo '		<meta property="og:image:height" content="'.intval($image_height).'" />'."\n";
				}
			}
		}
		
		/*
			 Function: getData
				 Returns Open Graph data for the specified table/id combination.
			
			Paremeters:
				table - The table for the entry
				id - The ID of the entry
		
			Returns:
				An array of Open Graph data.
		*/
		
		public static function getData(string $table, $id): array
		{
			try {
				$og = SQL::fetch("SELECT * FROM bigtree_open_graph WHERE `table` = ? AND `entry` = ?", $table, $id);
			} catch (Exception $e) {
				$og = null;
			}
			
			if (!$og) {
				return [
					"title" => "",
					"description" => "",
					"type" => "",
					"image" => ""
				];
			}
			
			return Link::decode($og);
		}
		
		/*
			Function: handleData
				Handles user input open graph data for an entry.

			Parameters:
				table - The table for the entry (not required for pending entry processing)
				id - The ID of the entry (not required for pending entry processing)
				data - Generic text data for open graph keys
				image - $_FILES array data for an open graph image
				pending - Whether this is a pending entry, if true returns the array of pending data to store

			Returns:
				An array of data if pending flag is true, otherwise the ID of the open graph table entry
		*/
		
		public static function handleData(?string $table, $id, array $data, ?array $image = null,
										  bool $pending = false)
		{
			if (!empty($table)) {
				SQL::delete("bigtree_open_graph", ["table" => $table, "entry" => $id]);
			}
			
			$og_image = null;
			$og_image_width = null;
			$og_image_height = null;
			
			if (!empty($image["tmp_name"])) {
				list($og_image_width, $og_image_height) = getimagesize($image["tmp_name"]);

				$image_obj = new Image($image["tmp_name"], [
					"directory" => "files/open-graph/",
					"min_width" => 1200,
					"min_height" => 630
				]);
				
				if (empty($image_obj->Error)) {
					$og_image = $image_obj->store($image["name"]);
				} else {
					ErrorHandler::logError(Text::translate("Open Graph Image"), $image_obj->Error);
				}
			}
			
			if (strpos($data["image"], "resource://") === 0) {
				$resource = Resource::getByFile(substr($data["image"], 11));
				
				if (!is_null($resource)) {
					$og_image = "irl://".$resource->ID;
					$og_image_width = $resource->Width;
					$og_image_height = $resource->Height;
					
				} else {
					ErrorHandler::logError(Text::translate("Open Graph Image"), Text::translate("Invalid image selected."));
					$og_image = null;
					$og_image_width = null;
					$og_image_height = null;
				}
			}
			
			$data = [
				"table" => $table,
				"entry" => $id,
				"title" => Text::htmlEncode($data["title"]),
				"description" => Text::htmlEncode($data["description"]),
				"type" => Text::htmlEncode($data["type"]),
				"image" => Text::htmlEncode($og_image ?: $data["image"]),
				"image_width" => $og_image ? $og_image_width : $data["image_width"],
				"image_height" => $og_image ? $og_image_height : $data["image_height"]
			];
			
			if ($pending) {
				return $data;
			}
			
			return SQL::insert("bigtree_open_graph", $data);
		}
		
		/*
			Function: setContext
				Sets the context for the open graph data.

			Parameters:
				table - A data table to pull open graph information from
				entry - The ID of the entry to pull open graph information for
				title - A page title to use (optional, will use Open Graph information if not entered)
				description - A meta description to use (optional, will use Open Graph information if not entered)
				image - An image to use for Open Graph (if OG data is empty)
				type - An Open Graph type to default to (if left empty and OG data is empty, will use "website")
		*/
		
		public static function setContext(string $table, $entry, ?string $title = null, ?string $description = null,
										  ?string $image = null, ?string $type = null): void
		{
			static::$Context = [
				"table" => $table,
				"entry" => $entry,
				"title" => $title,
				"description" => $description,
				"image" => $image,
				"type" => $type
			];
		}
		
	}
	