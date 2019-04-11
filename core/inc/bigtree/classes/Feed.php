<?php
	/*
		Class: BigTree\Feed
			Provides an interface for handling BigTree feeds.
	*/
	
	namespace BigTree;
	
	class Feed extends BaseObject
	{
		
		protected $ID;
		
		public $Description;
		public $Fields;
		public $Name;
		public $Route;
		public $Settings;
		public $Type;
		
		public static $AvailableTypes = [
			"custom" => "XML",
			"json" => "JSON",
			"rss" => "RSS 0.91",
			"rss2" => "RSS 2.0"
		];
		public static $Table = "bigtree_feeds";
		
		/*
			Constructor:
				Builds a Feed object referencing an existing database entry.

			Parameters:
				feed - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($feed = null)
		{
			if ($feed !== null) {
				// Passing in just an ID
				if (!is_array($feed)) {
					$feed = SQL::fetch("SELECT * FROM bigtree_feeds WHERE id = ?", $feed);
				}
				
				// Bad data set
				if (!is_array($feed)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $feed["id"];
					
					$this->Description = $feed["description"];
					$this->Fields = json_decode($feed["fields"], true);
					$this->Name = $feed["name"];
					$this->Route = $feed["route"];
					$this->Settings = json_decode($feed["options"], true);
					$this->Table = $feed["table"];
					$this->Type = $feed["type"];
				}
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
		
		public static function create(string $name, string $description, string $table, string $type,
									  array $settings, array $fields): Feed
		{
			// Settings were probably passed as a JSON string, but either way make a nice translated array
			$settings = Link::encode($settings);
			
			// Get a unique route!
			$route = SQL::unique("bigtree_feeds", "route", Link::urlify($name));
			
			// Insert and track
			$id = SQL::insert("bigtree_feeds", [
				"route" => $route,
				"name" => Text::htmlEncode($name),
				"description" => Text::htmlEncode($description),
				"type" => $type,
				"table" => $table,
				"fields" => $fields,
				"options" => $settings
			]);
			
			AuditTrail::track("bigtree_feeds", $id, "created");
			
			return new Feed($id);
		}
		
		/*
			Function: save
				Saves the object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			if (empty($this->ID)) {
				$new = static::create($this->Name, $this->Description, $this->Table, $this->Type, $this->Settings, $this->Fields);
				$this->inherit($new);
			} else {
				SQL::update("bigtree_feeds", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"description" => Text::htmlEncode($this->Description),
					"table" => $this->Table,
					"type" => $this->Type,
					"fields" => $this->Fields,
					"options" => Link::encode($this->Settings)
				]);
				AuditTrail::track("bigtree_feeds", $this->ID, "updated");
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the feed's properties and saves them back to the database.

			Parameters:
				name - The name.
				description - The description.
				table - The data table.
				type - The feed type.
				settings - The feed type settings.
				fields - The fields.
		*/
		
		public function update(string $name, string $description, string $table, string $type, array $settings,
							   array $fields): ?bool
		{
			$settings = is_array($settings) ? $settings : json_decode($settings, true);
			
			$this->Name = $name;
			$this->Description = $description;
			$this->Table = $table;
			$this->Type = $type;
			$this->Settings = $settings;
			$this->Fields = $fields;
			
			return $this->save();
		}
		
	}
