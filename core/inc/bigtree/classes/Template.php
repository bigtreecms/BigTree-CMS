<?php
	/*
		Class: BigTree\Template
			Provides an interface for handling BigTree templates.
	*/
	
	namespace BigTree;
	
	class Template extends BaseObject {
		
		public static $Table = "bigtree_templates";
		
		protected $ID;
		protected $Routed;
		
		public $Extension;
		public $Fields;
		public $Level;
		public $Module;
		public $Name;
		public $Position;
		public $Resources;
		
		/*
			Constructor:
				Builds a Template object referencing an existing database entry.

			Parameters:
				template - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		function __construct($template = null) {
			if ($template !== null) {
				// Passing in just an ID
				if (!is_array($template)) {
					$template = SQL::fetch("SELECT * FROM bigtree_templates WHERE id = ?", $template);
				}
				
				// Bad data set
				if (!is_array($template)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $template["id"];
					
					$this->Extension = $template["extension"];
					$this->Fields = Link::decode(array_filter((array) @json_decode($template["resources"], true)));
					$this->Level = $template["level"];
					$this->Module = $template["module"];
					$this->Name = $template["name"];
					$this->Position = $template["position"];
					$this->Resources = &$this->Fields; // Backwards compat
					$this->Routed = $template["route"] ? true : false;
				}
			}
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

			Returns:
				Template object if successful, null if there's an ID collision or a bad ID is passed
		*/
		
		static function create(string $id, string $name, bool $routed, int $level, ?int $module, array $fields): ?Template {
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(["-", "_"], "", $id)) || strlen($id) > 127) {
				return null;
			}
			
			// Check to see if the id already exists
			if (SQL::exists("bigtree_templates", $id)) {
				return null;
			}
			
			// If we're creating a new file, let's populate it with some convenience things to show what resources are available.
			$file_contents = "<?\n	/*\n		Fields Available:\n";
			
			// Grabbing field types so we can put their name in the template file
			$types = FieldType::reference(false, "templates");
			
			// Loop through fields and create cleaned up versions
			$fields = array_filter((array) $fields);
			
			foreach ($fields as $key => $field) {
				if (!$field["id"]) {
					unset($fields[$key]);
				} else {
					$options = is_array($field["options"]) ? $field["options"] : json_decode($field["options"], true);
					
					$field_data = [
						"id" => Text::htmlEncode($field["id"]),
						"type" => Text::htmlEncode($field["type"]),
						"title" => Text::htmlEncode($field["title"]),
						"subtitle" => Text::htmlEncode($field["subtitle"]),
						"options" => Link::encode((array) $options)
					];
					
					// Backwards compatibility with BigTree 4.1 package imports
					foreach ($field as $sub_key => $value) {
						if (!in_array($sub_key, ["id", "title", "subtitle", "type", "options"])) {
							$field_data["options"][$sub_key] = $value;
						}
					}
					
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
			SQL::query("UPDATE bigtree_templates SET position = position + 1");
			
			// Insert template
			SQL::insert("bigtree_templates", [
				"id" => $id,
				"name" => Text::htmlEncode($name),
				"module" => $module,
				"resources" => $fields,
				"level" => $level,
				"routed" => $routed ? "on" : ""
			]);
			
			AuditTrail::track("bigtree_templates", $id, "created");
			
			return new Template($id);
		}
		
		/*
			Function: delete
				Deletes the template and its related files.
		*/
		
		function delete(): ?bool {
			// Delete related files
			if ($this->Routed) {
				FileSystem::deleteDirectory(SERVER_ROOT."templates/routed/".$this->ID."/");
			} else {
				FileSystem::deleteFile(SERVER_ROOT."templates/basic/".$this->ID.".php");
			}
			
			SQL::delete("bigtree_templates", $this->ID);
			
			AuditTrail::track("bigtree_templates", $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		function save(): ?bool {
			// Templates specify their own ID so we check for DB existance to determine save behavior
			if (!SQL::exists("bigtree_templates", $this->ID)) {
				$new = static::create($this->ID, $this->Name, $this->Routed, $this->Level, $this->Module, $this->Fields);
				$this->inherit($new);
			} else {
				// Clean up fields
				$fields = [];
				
				foreach ($this->Fields as $field) {
					if ($field["id"]) {
						$options = is_array($field["options"]) ? $field["options"] : json_decode($field["options"], true);
						
						$fields[] = [
							"id" => Text::htmlEncode($field["id"]),
							"title" => Text::htmlEncode($field["title"]),
							"subtitle" => Text::htmlEncode($field["subtitle"]),
							"type" => Text::htmlEncode($field["type"]),
							"options" => Link::encode((array) $options)
						];
					}
				}
				
				// Update DB
				SQL::update("bigtree_templates", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"resources" => array_filter($fields),
					"module" => $this->Module,
					"level" => $this->Level,
					"position" => $this->Position
				]);
				
				// Track
				AuditTrail::track("bigtree_templates", $this->ID, "updated");
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
		*/
		
		function update(string $name, int $level, ?int $module, array $fields): void {
			$this->Fields = $fields;
			$this->Level = $level;
			$this->Module = $module;
			$this->Name = $name;
			
			$this->save();
		}
		
	}
	