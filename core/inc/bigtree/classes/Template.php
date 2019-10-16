<?php
	/*
		Class: BigTree\Template
			Provides an interface for handling BigTree templates.
	*/
	
	namespace BigTree;
	
	class Template extends JSONObject
	{
		
		protected $ID;
		protected $Routed;
		protected $Resources;
		
		public $Extension;
		public $Fields = [];
		public $Hooks = [
			"edit" => null,
			"pre" => null,
			"post" => null,
			"publish" => null
		];
		public $Level;
		public $Module;
		public $Name;
		public $Position;
		
		public static $Store = "templates";
		
		/*
			Constructor:
				Builds a Template object referencing an existing database entry.

			Parameters:
				template - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($template = null)
		{
			if ($template !== null) {
				// Passing in just an ID
				if (!is_array($template)) {
					$template = DB::get("templates", $template);
				}
				
				// Bad data set
				if (!is_array($template)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $template["id"];
					
					$this->Extension = $template["extension"];
					$this->Fields = Link::decode($template["fields"]);
					$this->Hooks = static::cleanHooks($template["hooks"]);
					$this->Level = $template["level"];
					$this->Module = $template["module"];
					$this->Name = $template["name"];
					$this->Position = $template["position"];
					$this->Routed = $template["routed"] ? true : false;
				}
			}
		}
		
		/*
			Function: cleanHooks
				Cleans up the hooks array for saving.
			
			Parameters:
				hooks - An array of hooks (or non-array value)
			
			Returns:
				A cleaned up array of hooks with the proper keys.
		*/
		
		public static function cleanHooks($hooks): array
		{
			if (!is_array($hooks)) {
				return ["pre" => "", "post" => "", "edit" => "", "publish" => ""];
			} else {
				$allowed_keys = ["pre", "post", "edit", "publish"];
				
				foreach ($hooks as $index => $hook) {
					if (!in_array($index, $allowed_keys)) {
						unset($hooks[$index]);
					}
				}
				
				foreach ($allowed_keys as $key) {
					if (!isset($hooks[$key])) {
						$hooks[$key] = "";
					}
				}
			}
			
			return $hooks;
		}
		
		/*
			Function: create
				Creates a template and its default files/directories.

			Parameters:
				id - Id for the template.
				name - Name
				routed - Basic ("") or Routed ("on")
				level - Access level (0 for everyone, 1 for administrators, 2 for developers)
				module - Related module id
				fields - An array of fields
				hooks - An array of hooks ("pre", "post", "edit", and "publish" keys)

			Returns:
				Template object if successful, null if there's an ID collision or a bad ID is passed
		*/
		
		public static function create(string $id, string $name, bool $routed, int $level, ?int $module, array $fields,
									  ?array $hooks = null): ?Template
		{
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(["-", "_"], "", $id)) || strlen($id) > 127) {
				return null;
			}
			
			// Check to see if the id already exists
			if (DB::exists("templates", $id)) {
				return null;
			}
			
			// If we're creating a new file, let's populate it with some convenience things to show what resources are available.
			$file_contents = "<?php\n	/*\n		Fields Available:\n";
			
			// Grabbing field types so we can put their name in the template file
			$types = FieldType::reference(false, "templates");
			
			// Loop through fields and create cleaned up versions
			$fields = array_filter((array) $fields);
			
			foreach ($fields as $key => $field) {
				if (!$field["id"]) {
					unset($fields[$key]);
				} else {
					$settings = is_array($field["settings"]) ? $field["settings"] : json_decode($field["settings"], true);
					
					$field_data = [
						"id" => Text::htmlEncode($field["id"]),
						"type" => Text::htmlEncode($field["type"]),
						"title" => Text::htmlEncode($field["title"]),
						"subtitle" => Text::htmlEncode($field["subtitle"]),
						"settings" => Link::encode(Utils::arrayFilterRecursive((array) $settings))
					];
					
					$fields[$key] = $field_data;
					
					$file_contents .= '		$'.$field["id"].' = '.$field["title"].' - '.$types[$field["type"]]["name"]."\n";
				}
			}
			
			$file_contents .= '	*/
?>';
			if (!count($fields)) {
				$file_contents = "";
			}
			
			if ($routed) {
				if (!file_exists(SERVER_ROOT."templates/routed/".$id."/default.php")) {
					FileSystem::createFile(SERVER_ROOT."templates/routed/".$id."/default.php", $file_contents);
				}
			} elseif (!file_exists(SERVER_ROOT."templates/basic/".$id.".php")) {
				FileSystem::createFile(SERVER_ROOT."templates/basic/".$id.".php", $file_contents);
			}
			
			// Increase the count of the positions on all templates by 1 so that this new template is for sure in last position.
			DB::incrementPosition("templates");
			
			// Insert template
			DB::insert("templates", [
				"id" => $id,
				"name" => Text::htmlEncode($name),
				"module" => $module,
				"fields" => $fields,
				"level" => $level,
				"routed" => $routed ? "on" : "",
				"hooks" => static::cleanHooks($hooks)
			]);
			
			AuditTrail::track("config:templates", $id, "add", "created");
			
			return new Template($id);
		}
		
		/*
			Function: delete
				Deletes the template and its related files.
		*/
		
		public function delete(): ?bool
		{
			if (!DB::exists("templates", $this->ID)) {
				return false;
			}
			
			// Delete related files
			if ($this->Routed) {
				FileSystem::deleteDirectory(SERVER_ROOT."templates/routed/".$this->ID."/");
			} else {
				FileSystem::deleteFile(SERVER_ROOT."templates/basic/".$this->ID.".php");
			}
			
			DB::delete("templates", $this->ID);
			AuditTrail::track("config:templates", $this->ID, "delete", "deleted");
			
			return true;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			// Templates specify their own ID so we check for DB existance to determine save behavior
			if (!DB::exists("templates", $this->ID)) {
				$new = static::create($this->ID, $this->Name, $this->Routed, $this->Level, $this->Module, $this->Fields,
									  $this->Hooks);
				$this->inherit($new);
			} else {
				// Clean up fields
				$fields = [];
				
				foreach ($this->Fields as $field) {
					if ($field["id"]) {
						$settings = is_array($field["settings"]) ? $field["settings"] : json_decode($field["settings"], true);
						
						$fields[] = [
							"id" => Text::htmlEncode($field["id"]),
							"title" => Text::htmlEncode($field["title"]),
							"subtitle" => Text::htmlEncode($field["subtitle"]),
							"type" => Text::htmlEncode($field["type"]),
							"settings" => Link::encode(Utils::arrayFilterRecursive((array) $settings))
						];
					}
				}
				
				// Update DB
				DB::update("templates", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"fields" => array_filter($fields),
					"module" => $this->Module,
					"level" => $this->Level,
					"position" => $this->Position,
					"hooks" => static::cleanHooks($this->Hooks)
				]);
				
				// Track
				AuditTrail::track("config:templates", $this->ID, "update", "updated");
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the template.

			Parameters:
				name - Name
				level - Access level (0 for everyone, 1 for administrators, 2 for developers)
				module - Related module id
				fields - An array of fields
				hooks - An array of hooks (pre, post, edit, and publish keys) or null
		*/
		
		public function update(string $name, int $level, ?int $module, array $fields, ?array $hooks): void
		{
			$this->Fields = $fields;
			$this->Hooks = $hooks;
			$this->Level = $level;
			$this->Module = $module;
			$this->Name = $name;
			
			$this->save();
		}
		
	}
	