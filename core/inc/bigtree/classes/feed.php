<?php
	/*
		Class: BigTree\Feed
			Provides an interface for handling BigTree feeds.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class Feed extends BaseObject {

		protected $ID;

		public $Description;
		public $Fields;
		public $Name;
		public $Route;
		public $Settings;
		public $Table;
		public $Type;

		/*
			Constructor:
				Builds a Feed object referencing an existing database entry.

			Parameters:
				feed - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($feed) {
			// Passing in just an ID
			if (!is_array($feed)) {
				$feed = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_feeds WHERE id = ?", $feed);
			}

			// Bad data set
			if (!is_array($feed)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $feed["id"];

				$this->Description = $feed["description"];
				$this->Fields = json_decode($feed["fields"]);
				$this->Name = $feed["name"];
				$this->Route = $feed["route"];
				$this->Settings = json_decode($feed["options"]);
				$this->Table = $feed["table"];
				$this->Type = $feed["type"];
			}
		}

		/*
			Function: create
				Creates a feed.

			Parameters:
				name - The name.
				description - The description.
				table - The data table.
				type - The feed type.
				settings - The feed type settings.
				fields - The fields.

			Returns:
				A Feed object.
		*/

		static function create($name,$description,$table,$type,$settings,$fields) {
			// Settings were probably passed as a JSON string, but either way make a nice translated array
			$settings = BigTree::translateArray(is_array($settings) ? $settings : array_filter((array)json_decode($settings,true)));

			// Get a unique route!
			$route = BigTreeCMS::$DB->unique("bigtree_feeds","route",BigTreeCMS::urlify($name));

			// Insert and track
			$id = BigTreeCMS::$DB->insert("bigtree_feeds",array(
				"route" => $route,
				"name" => BigTree::safeEncode($name),
				"description" => BigTree::safeEncode($description),
				"type" => $type,
				"table" => $table,
				"fields" => $fields,
				"options" => $settings
			));

			AuditTrail::track("bigtree_feeds",$id,"created");

			return new Feed($id);
		}

	}
