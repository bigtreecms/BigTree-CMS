<?php
	/*
		Class: BigTree\Feed
			Provides an interface for handling BigTree feeds.
	*/
	
	namespace BigTree;
	
	class Feed extends JSONObject
	{
		
		protected $ID;
		
		public $Description;
		public $Fields;
		public $Name;
		public $Route;
		public $Settings;
		public $Table;
		public $Type;
		
		public static $AvailableTypes = [
			"custom" => "XML",
			"json" => "JSON",
			"rss" => "RSS 0.91",
			"rss2" => "RSS 2.0"
		];
		public static $Store = "feeds";
		
		/*
			Constructor:
				Builds a Feed object referencing an existing database entry.

			Parameters:
				feed - Either an ID (to pull a record) or an array (to use the array as the record)
				on_fail - An optional callable to call on non-exist or bad data (rather than triggering an error).
		*/
		
		public function __construct($feed = null, ?callable $on_fail = null)
		{
			if ($feed !== null) {
				// Passing in just an ID
				if (!is_array($feed)) {
					$feed = DB::get("feeds", $feed);
				}
				
				// Bad data set
				if (!is_array($feed)) {
					if ($on_fail) {
						return $on_fail();
					} else {
						trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
					}
				} else {
					$this->ID = $feed["id"];
					
					$this->Description = $feed["description"];
					$this->Fields = $feed["fields"];
					$this->Name = $feed["name"];
					$this->Route = $feed["route"];
					$this->Settings = $feed["settings"];
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
			$settings = Link::encode($settings);
			$route = DB::unique("feeds", "route", Link::urlify($name));
			$id = DB::insert("feeds", [
				"route" => $route,
				"name" => Text::htmlEncode($name),
				"description" => Text::htmlEncode($description),
				"type" => $type,
				"table" => $table,
				"fields" => $fields,
				"settings" => $settings
			]);
			
			AuditTrail::track("config:feeds", $id, "add", "created");
			
			return new Feed($id);
		}
		
		/*
			Function: save
				Saves the object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			if (empty($this->ID)) {
				$new = static::create($this->Name, $this->Description, $this->Table, $this->Type, $this->Settings,
									  $this->Fields);
				$this->inherit($new);
			} else {
				DB::update("feeds", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"description" => Text::htmlEncode($this->Description),
					"table" => $this->Table,
					"type" => $this->Type,
					"fields" => $this->Fields,
					"settings" => Link::encode($this->Settings)
				]);
				AuditTrail::track("config:feeds", $this->ID, "update", "updated");
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
			$this->Name = $name;
			$this->Description = $description;
			$this->Table = $table;
			$this->Type = $type;
			$this->Settings = $settings;
			$this->Fields = $fields;
			
			return $this->save();
		}
		
	}
