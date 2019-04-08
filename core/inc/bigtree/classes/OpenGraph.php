<?php
	/*
		Class: BigTree\OpenGraph
			Provides an interface for handling BigTree Open Graph data.
	*/
	
	namespace BigTree;
	use Exception;
	
	class OpenGraph
	{
		
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
					"type" => "website",
					"image" => ""
				];
			}
			
			return Link::decode($og);
		}
		
		/*
			Function: handleData
				Handles user input open graph data for an entry.

			Parameters:
				table - The table for the entry
				id - The ID of the entry
				data - Generic text data for open graph keys
				image - $_FILES array data for an open graph image
				pending - Whether this is a pending entry, if true returns the array of pending data to store

			Returns:
				An array of data if pending flag is true, otherwise the ID of the open graph table entry
		*/
		
		public static function handleData(string $table, $id, array $data, ?array $image = null, bool $pending = false)
		{
			SQL::delete("bigtree_open_graph", ["table" => $table, "entry" => $id]);
			
			if (!empty($image["tmp_name"])) {
				$image_obj = new Image($image["tmp_name"], [
					"directory" => "files/open-graph/",
					"min_width" => 1200,
					"min_height" => 630
				]);
				
				if (empty($image_obj->Error)) {
					$data["image"] = $image_obj->store($image["name"]);
				} else {
					ErrorHandler::logError(Text::translate("Open Graph Image"), $image_obj->Error);
				}
			}
			
			if (strpos($data["image"], "resource://") === 0) {
				$resource = Resource::getByFile(substr($data["image"], 11));
				
				if (!is_null($resource)) {
					$data["image"] = "irl://".$resource->ID;
				} else {
					ErrorHandler::logError(Text::translate("Open Graph Image"), Text::translate("Invalid image selected."));
					$data["image"] = "";
				}
			}
			
			$data = [
				"table" => $table,
				"entry" => $id,
				"title" => Text::htmlEncode($data["title"]),
				"description" => Text::htmlEncode($data["description"]),
				"type" => Text::htmlEncode($data["type"]),
				"image" => Text::htmlEncode($data["image"])
			];
			
			if ($pending) {
				return $data;
			}
			
			return SQL::insert("bigtree_open_graph", $data);
		}
		
	}
	