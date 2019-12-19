<?php
	/*
		Class: BigTree\Callout
			Provides an interface for handling BigTree callouts.
	*/
	
	namespace BigTree;
	
	class Callout extends JSONObject
	{
		
		public $Description;
		public $Extension;
		public $Fields;
		public $ID;
		public $Level;
		public $Name;
		public $Position;
		
		public static $Store = "callouts";
		
		/*
			Constructor:
				Builds a Callout object referencing an existing database entry.

			Parameters:
				callout - Either an ID (to pull a record) or an array (to use the array as the record)
				on_fail - An optional callable to call on non-exist or bad data (rather than triggering an error).
		*/
		
		public function __construct($callout = null, ?callable $on_fail = null)
		{
			if ($callout !== null) {
				// Passing in just an ID
				if (!is_array($callout)) {
					$callout = DB::get("callouts", $callout);
				}
				
				// Bad data set
				if (!is_array($callout)) {
					if ($on_fail) {
						return $on_fail();
					} else {
						trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
					}
				} else {
					$this->ID = $callout["id"];
					$this->Description = $callout["description"];
					$this->Extension = $callout["extension"];
					$this->Fields = Link::decode($callout["fields"]);
					$this->Level = $callout["level"];
					$this->Name = $callout["name"];
					$this->Position = $callout["position"];
				}
			}
		}
		
		/*
			Function: allAllowed
				Returns a list of callouts the logged-in user is allowed access to.

			Parameters:
				sort - The order to return the callouts. Defaults to positioned.
				return_arrays - Set to true to return arrays of data rather than objects.

			Returns:
				An array of callout entries from the JSON store.
		*/
		
		public static function allAllowed(string $sort = "position", bool $return_arrays = false): ?array
		{
			$user = Auth::user()->ID;
			
			// Make sure a user is logged in
			if (is_null($user)) {
				trigger_error("Method allAllowed not available outside logged-in user context.", E_USER_ERROR);
				
				return null;
			}
			
			$callouts = DB::getAll("callouts", $sort);
			
			foreach ($callouts as $index => $callout) {
				if ($callout["level"] > Auth::user()->Level) {
					unset($callouts[$index]);
				}
			}
			
			// Return objects
			if (!$return_arrays) {
				foreach ($callouts as &$callout) {
					$callout = new Callout($callout);
				}
			}
			
			return $callouts;
		}
		
		/*
			Function: allInGroups
				Returns a list of callouts in a given set of groups.

			Parameters:
				groups - An array of group IDs to retrieve callouts for.
				auth - If set to true, only returns callouts the logged in user has access to. Defaults to true.
				return_arrays - Set to true to return arrays of data rather than objects.

			Returns:
				An alphabetized array of callouts from the JSON store.
		*/
		
		public static function allInGroups(array $groups, bool $auth = true, bool $return_arrays = false): ?array
		{
			$ids = $callouts = $names = [];
			$user = Auth::user()->ID;
			
			// Make sure a user is logged in
			if ($auth && is_null($user)) {
				trigger_error("Method allInGroups not available outside logged-in user context when passing auth = true.", E_USER_ERROR);
				
				return null;
			}
			
			foreach ($groups as $group_id) {
				$group = new CalloutGroup($group_id);
				
				if (empty($group->ID)) {
					continue;
				}
				
				foreach ($group->Callouts as $callout_id) {
					// Only grab each callout once
					if (!in_array($callout_id, $ids)) {
						$callout = DB::get("callouts", $callout_id);
						$ids[] = $callout_id;
						
						// If we're looking at only the ones the user is allowed to access, check levels
						if (!$auth || Auth::user()->Level >= $callout["level"]) {
							$callouts[] = $callout;
							$names[] = $callout["name"];
						}
					}
				}
			}
			
			array_multisort($names, $callouts);
			
			// Return objects
			if (!$return_arrays) {
				foreach ($callouts as &$callout) {
					$callout = new Callout($callout);
				}
			}
			
			return $callouts;
		}
		
		/*
			Function: create
				Creates a callout and its files.

			Parameters:
				id - The id.
				name - The name.
				description - The description.
				level - Access level (0 for everyone, 1 for administrators, 2 for developers).
				fields - An array of fields.

			Returns:
				A Callout object if successful, null if an invalid ID was passed or the ID is already in use
		*/
		
		public static function create(string $id, string $name, string $description, int $level,
									  array $fields): ?Callout
		{
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(["-", "_"], "", $id)) || strlen($id) > 127) {
				return null;
			}
			
			// See if a callout ID already exists
			if (DB::exists("callouts", $id)) {
				return null;
			}
			
			// If we're creating a new file, let's populate it with some convenience things to show what fields are available.
			$file_contents = '<?php
	/*
		Fields Available:
';
			
			$cached_types = FieldType::reference();
			$types = $cached_types["callouts"];
			
			foreach ($fields as $key => $field) {
				// "type" is still a reserved keyword due to the way we save callout data when editing.
				if (!$field["id"] || $field["id"] == "type") {
					unset($fields[$key]);
				} else {
					$settings = is_array($field["settings"]) ? $field["settings"] : json_decode($field["settings"], true);
					
					$field = [
						"id" => Text::htmlEncode($field["id"]),
						"type" => Text::htmlEncode($field["type"]),
						"title" => Text::htmlEncode($field["title"]),
						"subtitle" => Text::htmlEncode($field["subtitle"]),
						"settings" => Link::encode(Utils::arrayFilterRecursive((array) $settings))
					];
					
					$fields[$key] = $field;
					
					$file_contents .= '		"'.$field["id"].'" = '.$field["title"].' - '.$types[$field["type"]]["name"]."\n";
				}
			}
			
			$file_contents .= '	*/
?>';
			
			// Create the template file if it doesn't yet exist
			if (!file_exists(SERVER_ROOT."templates/callouts/$id.php")) {
				FileSystem::createFile(SERVER_ROOT."templates/callouts/$id.php", $file_contents);
			}
						
			// Insert the callout
			DB::insert("callouts", [
				"id" => Text::htmlEncode($id),
				"name" => Text::htmlEncode($name),
				"description" => Text::htmlEncode($description),
				"fields" => $fields,
				"level" => $level
			]);
			
			AuditTrail::track("config:callouts", $id, "add", "created");
			
			return new Callout($id);
		}
		
		/*
			Function: delete
				Deletes the callout and removes its file.
			
			Returns:
				True on success
		*/
		
		public function delete(): ?bool
		{
			$id = $this->ID;
			
			if (!DB::exists("callouts", $id)) {
				return false;
			}
			
			// Delete template file
			unlink(SERVER_ROOT."templates/callouts/$id.php");
			
			// Delete callout
			DB::delete("callouts", $id);
			
			// Remove the callout from any groups it lives in
			$groups = DB::getAll("callout-groups");
			
			foreach ($groups as $group) {
				if (is_array($group["callouts"])) {
					foreach ($group["callouts"] as $index => $callout_id) {
						if ($callout_id == $id) {
							unset($group["callouts"][$index]);
							DB::update("callout-groups", $group["id"], ["callouts" => $$group["callouts"]]);
						}
					}
				}
			}
			
			AuditTrail::track("config:callouts", $id, "delete", "deleted");
			
			return true;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			// Callouts set their own ID, so we need to check the database to see if the ID already exists before updating/creating
			if (DB::exists("callouts", $this->ID)) {
				// Clean up fields
				$fields = [];
				
				foreach ($this->Fields as $field) {
					// "type" is still a reserved keyword due to the way we save callout data when editing.
					if ($field["id"] && $field["id"] != "type") {
						$settings = is_array($field["settings"]) ? $field["settings"] : json_decode($field["settings"], true);
						
						$fields[] = [
							"id" => Text::htmlEncode($field["id"]),
							"type" => Text::htmlEncode($field["type"]),
							"title" => Text::htmlEncode($field["title"]),
							"subtitle" => Text::htmlEncode($field["subtitle"]),
							"settings" => Link::encode(Utils::arrayFilterRecursive((array) $settings))
						];
					}
				}
				
				DB::update("callouts", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"description" => Text::htmlEncode($this->Description),
					"fields" => $fields,
					"level" => $this->Level,
					"position" => $this->Position,
					"extension" => $this->Extension
				]);
				
				AuditTrail::track("config:callouts", $this->ID, "update", "updated");
			} else {
				$new = static::create($this->ID, $this->Name, $this->Description, $this->Level, $this->Fields);
				
				if ($new !== false) {
					$this->inherit($new);
				} else {
					trigger_error("Failed to create new callout object due to invalid or in-use ID.", E_USER_WARNING);
					
					return null;
				}
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the callout properties and saves changes to the database.

			Parameters:
				name - The name.
				description - The description.
				level - The access level (0 for all users, 1 for administrators, 2 for developers)
				fields - An array of fields.
				display_field - The field to use as the display field describing a user's callout
				display_default - The text string to use in the event the display_field is blank or non-existent
		*/
		
		public function update(string $name, string $description, int $level, array $fields): ?bool
		{
			$this->Name = $name;
			$this->Description = $description;
			$this->Level = $level;
			$this->Fields = $fields;
			
			return $this->save();
		}
		
	}
