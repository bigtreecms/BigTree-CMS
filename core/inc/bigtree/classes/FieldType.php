<?php
	/*
		Class: BigTree\FieldType
			Provides an interface for handling BigTree field types.
	*/
	
	namespace BigTree;
	
	class FieldType extends JSONObject
	{
		
		public $ID;
		public $Name;
		public $SelfDraw;
		public $UseCases;
		
		public static $Store = "field-types";
		
		/*
			Constructor:
				Builds a FieldType object referencing an existing database entry.

			Parameters:
				field_type - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($field_type = null)
		{
			// Passing in just an ID
			if (!is_array($field_type)) {
				$field_type = DB::get("field-types", $field_type);
			}
			
			// Bad data set
			if (!is_array($field_type)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
			} else {
				$this->ID = $field_type["id"];
				$this->Name = $field_type["name"];
				$this->SelfDraw = !empty($field_type["self_draw"]) ? true : false;
				$this->UseCases = $field_type["use_cases"];
			}
		}
		
		/*
			Function: create
				Creates a field type and its files.

			Parameters:
				id - The id of the field type.
				name - The name.
				use_cases - Associate array of sections in which the field type can be used (i.e. ["pages" => "on", "modules" => "", "callouts" => "", "settings" => ""])
				self_draw - Whether this field type will draw its <fieldset> and <label> ("on" or a falsey value)

			Returns:
				A BigTree\FieldType object if successful, null if an invalid ID was passed or the ID is already in use
		*/
		
		public static function create(string $id, string $name, array $use_cases, bool $self_draw): ?FieldType
		{
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(["-", "_"], "", $id)) || strlen($id) > 127) {
				return null;
			}
			
			// See if a callout ID already exists
			if (DB::exists("field-types", $id)) {
				return null;
			}
			
			DB::insert("field-types", [
				"id" => $id,
				"name" => Text::htmlEncode($name),
				"use_cases" => $use_cases,
				"self_draw" => ($self_draw ? "on" : null)
			]);
			
			// Make the files for draw and process and options if they don't exist.
			if (!file_exists(SERVER_ROOT."custom/admin/field-types/$id/draw.php")) {
				FileSystem::createFile(SERVER_ROOT."custom/admin/field-types/$id/draw.php", '<?php
	namespace BigTree;
	
	/*
		When drawing a field type you are within the scope of a Field object ($this) with the following properties:
			Title — The title given by the developer to draw as the label (drawn automatically)
			Subtitle — The subtitle given by the developer to draw as the smaller part of the label (drawn automatically)
			Key — The value you should use for the "name" attribute of your form field
			Value — The existing value for this form field
			ID — A unique ID you can assign to your form field for use in JavaScript
			TabIndex — The current tab index you can use for the "tabindex" attribute of your form field
			Settings — An array of settings provided by the developer
			Required — A boolean value of whether this form field is required or not
	*/

	include Router::getIncludePath("admin/field-types/text/draw.php");');
			}
			
			if (!file_exists(SERVER_ROOT."custom/admin/field-types/$id/process.php")) {
				FileSystem::createFile(SERVER_ROOT."custom/admin/form-field-types/$id/process.php", '<?php
	/*
		When processing a field type you are within the scope of a Field object ($this) with the following properties:
			Key — The key of the field (this could be the database column for a module or the ID of the template or callout resource)
			Settings — An array of settings provided by the developer
			Input — The end user\'s $_POST data input for this field
			FileInput — The end user\'s uploaded files for this field in a normalized entry from the $_FILES array in the same formatting you\'d expect from "input"

		BigTree expects you to set $this->Output to the value you wish to store. If you want to ignore this field, set $this->Ignore to true.
		Almost all text that is meant for drawing on the front end is expected to be run through PHP\'s htmlspecialchars function as seen in the example below.
		If you intend to allow HTML tags you will want to run htmlspecialchars in your drawing file on your value and leave it off in the process file.
	*/

	$this->Output = htmlspecialchars($this->Input);');
			}
			
			if (!file_exists(SERVER_ROOT."custom/admin/field-types/$id/settings.php")) {
				FileSystem::touchFile(SERVER_ROOT."custom/admin/field-types/$id/settings.php");
			}
			
			FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");
			AuditTrail::track("config:field-types", $id, "created");
			
			return new FieldType($id);
		}
		
		/*
			Function: delete
				Deletes the field type and erases its files.
		*/
		
		public function delete(): ?bool
		{
			$id = $this->ID;
			
			if (!DB::exists("field-types", $id)) {
				return false;
			}
			
			// Remove related files
			FileSystem::deleteFile(SERVER_ROOT."custom/admin/form-field-types/draw/$id.php");
			FileSystem::deleteFile(SERVER_ROOT."custom/admin/form-field-types/process/$id.php");
			FileSystem::deleteFile(SERVER_ROOT."custom/admin/ajax/developer/field-options/$id.php");
			
			// Clear cache
			FileSystem::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");
			
			// Delete and track
			DB::delete("field-types", $id);
			AuditTrail::track("config:field-types", $id, "deleted");
			
			return true;
		}
		
		/*
			Function: reference
				Caches available field types and returns a reference array for them.

			Parameters:
				split - Whether to split the field types into separate default / custom arrays (defaults to false)
				use_case_type - A certain use case (i.e. "callouts" or "modules" -- defaults to all)

			Returns:
				Array of three arrays of field types (template, module, and callout).
		*/
		
		public static function reference(bool $split = false, ?string $use_case_type = null)
		{
			// Used cached values if available, otherwise query the DB
			if (file_exists(SERVER_ROOT."cache/bigtree-form-field-types.json")) {
				$types = json_decode(file_get_contents(SERVER_ROOT."cache/bigtree-form-field-types.json"), true);
			} else {
				$types = [];
				$types["modules"] = $types["templates"] = $types["callouts"] = $types["settings"] = [
					"default" => [
						"text" => ["name" => "Text", "self_draw" => false],
						"textarea" => ["name" => "Text Area", "self_draw" => false],
						"html" => ["name" => "HTML Area", "self_draw" => false],
						"link" => ["name" => "Link", "self_draw" => false],
						"upload" => ["name" => "File Upload", "self_draw" => false],
						"image" => ["name" => "Image Upload", "self_draw" => false],
						"video" => ["name" => "YouTube or Vimeo Video", "self_draw" => false],
						"file-reference" => ["name" => "File Reference", "self_draw" => false],
						"image-reference" => ["name" => "Image Reference", "self_draw" => false],
						"video-reference" => ["name" => "Video Reference", "self_draw" => false],
						"list" => ["name" => "List", "self_draw" => false],
						"checkbox" => ["name" => "Checkbox", "self_draw" => false],
						"date" => ["name" => "Date Picker", "self_draw" => false],
						"time" => ["name" => "Time Picker", "self_draw" => false],
						"datetime" => ["name" => "Date &amp; Time Picker", "self_draw" => false],
						"media-gallery" => ["name" => "Media Gallery", "self_draw" => false],
						"callouts" => ["name" => "Callouts", "self_draw" => true],
						"matrix" => ["name" => "Matrix", "self_draw" => true],
						"one-to-many" => ["name" => "One to Many", "self_draw" => false]
					],
					"custom" => []
				];
				
				$types["modules"]["default"]["route"] = ["name" => "Generated Route", "self_draw" => true];
				$field_types = DB::getAll("field-types", "name");
				
				foreach ($field_types as $field_type) {
					foreach ($field_type["use_cases"] as $case => $val) {
						if ($val) {
							$types[$case]["custom"][$field_type["id"]] = [
								"name" => $field_type["name"],
								"self_draw" => $field_type["self_draw"]
							];
						}
					}
				}
				
				FileSystem::createFile(SERVER_ROOT."cache/bigtree-form-field-types.json", JSON::encode($types));
			}
			
			// Re-merge if we don't want them split
			if (!$split) {
				foreach ($types as $use_case => $list) {
					$types[$use_case] = array_merge($list["default"], $list["custom"]);
				}
			}
			
			// If we only want one use case
			if (!is_null($use_case_type)) {
				return $types[$use_case_type];
			}
			
			return $types;
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			// IDs are user defined so the database entry may not exist but an ID still exists
			if (DB::exists("field-types", $this->ID)) {
				DB::update("field-types", $this->ID, [
					"name" => Text::htmlEncode($this->Name),
					"use_cases" => array_filter((array) $this->UseCases),
					"self_draw" => $this->SelfDraw ? "on" : ""
				]);
				AuditTrail::track("config:field-types", $this->ID, "updated");
				
				// Clear cache
				@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");
			} else {
				$new = static::create($this->ID, $this->Name, $this->UseCases, $this->SelfDraw);
				
				if ($new !== false) {
					$this->inherit($new);
				} else {
					trigger_error("Failed to create new field type due to invalid ID.", E_USER_WARNING);
					
					return null;
				}
			}
			
			return true;
		}
		
		/*
			Function: update
				Updates the field type's properties and saves them back to the database.

			Parameters:
				name - The name.
				use_cases - Associate array of sections in which the field type can be used (i.e. ["pages" => "on", "modules" => "", "callouts" => "", "settings" => ""])
				self_draw - Whether this field type will draw its <fieldset> and <label> ("on" or a falsey value)
		*/
		
		public function update(string $name, array $use_cases, bool $self_draw): ?bool {
			$this->Name = $name;
			$this->UseCases = $use_cases;
			$this->SelfDraw = $self_draw;
			
			return $this->save();
		}
		
	}
