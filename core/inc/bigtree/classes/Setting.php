<?php
	/*
		Class: BigTree\Setting
			Provides an interface for handling BigTree settings.
	*/

	namespace BigTree;

	use BigTree;

	class Setting extends BaseObject {

		protected $AutoSave;
		protected $OriginalEncrypted;
		protected $OriginalID;
		protected $OriginalValue;

		public $Description;
		public $Encrypted;
		public $Extension;
		public $ID;
		public $Locked;
		public $Name;
		public $Settings;
		public $System;
		public $Type;
		public $Value;

		/*
			Constructor:
				Builds a Setting object referencing an existing database entry.

			Parameters:
				setting - Either an ID (to pull a record) or an array (to use the array as the record)
				decode - Whether to decode the setting's value (defaults true, set to false for faster processing of large data value)
				auto_save - Automatically save this setting's value on destruction of the object (defaults to false)
		*/

		function __construct($setting, $decode = true, $auto_save = false) {
			global $bigtree;

			// Passing in just an ID
			if (!is_array($setting)) {
				$id = static::context($setting);
				$setting = SQL::fetch("SELECT * FROM bigtree_settings WHERE id = ?", $id);
			}

			// Bad data set
			if (!is_array($setting)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->Description = $setting["description"];
				$this->Encrypted = $this->OriginalEncrypted = $setting["encrypted"] ? true : false;
				$this->Extension = $setting["extension"];
				$this->ID = $this->OriginalID = $setting["id"];
				$this->Locked = $setting["locked"] ? true : false;
				$this->Name = $setting["name"];
				$this->Settings = BigTree::untranslateArray(is_string($setting["options"]) ? json_decode($setting["options"],true) : $setting["options"]);
				$this->System = $setting["system"] ? true : false;
				$this->Type = $setting["type"];

				// Value may be encrypted
				if ($this->Encrypted) {
					$value = SQL::fetchSingle("SELECT AES_DECRYPT(`value`,?) AS `value` FROM bigtree_settings 
											   WHERE id = ?", $bigtree["config"]["settings_key"], $this->ID);
				} else {
					$value = $setting["value"];
				}
				
				// Decode value
				$value = json_decode($value, true);
				if ($decode) {
					$value = is_string($value) ? Link::decode($value) : BigTree::untranslateArray($value);
				}

				$this->Value = $this->OriginalValue = $value;

				$this->AutoSave = $auto_save;
			}
		}

		/*
			Destructor:
				Saves the setting's value back to the database if it was instantiated with AutoSave.
		*/

		function __destruct() {
			global $bigtree;

			if ($this->AutoSave) {
				if ($this->Encrypted) {
					SQL::query("UPDATE bigtree_settings SET `value` = AES_ENCRYPT(?,?) WHERE id = ?",
							    $this->Value, $bigtree["config"]["settings_key"], $this->ID);
				} else {
					SQL::update("bigtree_settings",$this->ID,array("value" => $this->Value));
				}
			}
		}

		/*
			Function: allSystem
				Returns an array of user defined (no bigtree-internal- prefix) system settings.

			Parameters:
				sort - Order to return the settings. Defaults to name ASC.
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of entries from bigtree_settings.
		*/

		static function allSystem($sort = "name ASC",$return_arrays = false) {
			$settings = SQL::fetchAll("SELECT * FROM bigtree_settings 
												   WHERE id NOT LIKE 'bigtree-internal-%' AND system != '' ORDER BY $sort");

			if (!$return_arrays) {
				foreach ($settings as &$setting) {
					$setting = new Setting($setting);
				}
			}

			return $settings;
		}

		/*
			Function: context
				Checks to see if we're in an extension and if we're requesting a setting attached to it.
				For example, if "test-setting" is requested and "com.test.extension*test-setting" exists it will be used.

			Parameters:
				id - Setting id

			Returns:
				An extension setting ID if one is found.
		*/

		static function context($id) {
			// See if we're in an extension
			if (defined("EXTENSION_ROOT")) {
				$extension = rtrim(str_replace(SERVER_ROOT."extensions/","",EXTENSION_ROOT),"/");
				
				// If we're already asking for it by it's namespaced name, don't append again.
				if (substr($id,0,strlen($extension)) == $extension) {
					return $id;
				}
				
				// See if namespaced version exists
				if (SQL::exists("bigtree_settings",array("id" => "$extension*$id"))) {
					return "$extension*$id";
				}
			}
			
			return $id;
		}

		/*
			Function: create
				Creates a setting.
				Supports pre-4.3 parameter syntax by passing an array as the id parameter.

			Parameters:
				id - Unique ID
				name - Name
				description - Description / instructions for the user editing the setting
				type - Field Type
				settings - An array of settings for the field type
				extension - Related extension ID (defaults to none unless an extension is calling createSetting)
				system - Whether to hide this from the Settings tab (defaults to false)
				encrypted - Whether to encrypt this setting in the database (defaults to false)
				locked - Whether to lock this setting to only developers (defaults to false)

			Returns:
				True if successful, false if a setting already exists with the ID given.
		*/

		static function create($id,$name = "",$description = "",$type = "",$settings = array(),$extension = "",$system = false,$encrypted = false,$locked = false) {			
			// If an extension is creating a setting, make it a reference back to the extension
			if (defined("EXTENSION_ROOT") && !$extension) {
				$extension = rtrim(str_replace(SERVER_ROOT."extensions/","",EXTENSION_ROOT),"/");
				
				// Don't append extension again if it's already being called via the namespace
				if (strpos($id,"$extension*") === false) {
					$id = "$extension*$id";
				}
			}

			// Check for ID collision
			if (SQL::exists("bigtree_settings",$id)) {
				return false;
			}

			// Create the setting
			SQL::insert("bigtree_settings",array(
				"id" => $id,
				"name" => BigTree::safeEncode($name),
				"description" => $description,
				"type" => BigTree::safeEncode($type),
				"options" => array_filter((array)$settings),
				"locked" => $locked ? "on" : "",
				"encrypted" => $encrypted ? "on" : "",
				"system" => $system ? "on" : "",
				"extension" => $extension ? $extension : null
			));

			AuditTrail::track("bigtree_settings",$id,"created");

			return new Setting($id);
		}

		/*
			Function: exists
				Determines whether a setting exists for a given id.

			Parameters:
				id - The setting id to check for.

			Returns:
				1 if the setting exists, otherwise 0.
		*/

		static function exists($id) {
			return SQL::exists("bigtree_settings",static::context($id));
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			global $bigtree;

			SQL::update("bigtree_settings",$this->OriginalID,array(
				"id" => $this->ID,
				"type" => $this->Type,
				"options" => array_filter((array) $this->Settings),
				"name" => BigTree::safeEncode($this->Name),
				"description" => $this->Description,
				"locked" => $this->Locked ? "on" : "",
				"system" => $this->System ? "on" : "",
				"encrypted" => $this->Encrypted ? "on" : ""
			));

			// If value has changed, set it now
			if ($this->Value != $this->OriginalValue) {
				// Do encoding
				if (is_array($this->Value)) {
					$value = BigTree::translateArray($this->Value);
				} else {
					$value = Link::encode($this->Value);
				}
	
				if ($this->Encrypted) {
					SQL::query("UPDATE bigtree_settings SET `value` = AES_ENCRYPT(?,?) WHERE id = ?", 
											$value, $bigtree["config"]["settings_key"], $this->ID);
				} else {
					SQL::update("bigtree_settings",$this->ID,array("value" => $value));
				}

			// If encryption status has changed, update the value directly
			} else {
				if ($this->OriginalEncrypted && !$this->Encrypted) {
					SQL::query("UPDATE bigtree_settings SET value = AES_DECRYPT(value, ?) WHERE id = ?",
								$bigtree["config"]["settings_key"], $this->ID);
				} elseif (!$this->OriginalEncrypted && $this->Encrypted) {
					SQL::query("UPDATE bigtree_settings SET value = AES_ENCRYPT(value, ?) WHERE id = ?",
								$bigtree["config"]["settings_key"], $this->ID);
				}
			}

			// If we changed IDs, leave a notice in the audit trail
			if ($this->OriginalID != $this->ID) {
				AuditTrail::track("bigtree_settings",$this->OriginalID,"changed-id");
			}
			AuditTrail::track("bigtree_settings",$this->ID,"updated");

			// Update "original" value tracking
			$this->OriginalEncrypted = $this->Encrypted;
			$this->OriginalID = $this->ID;
			$this->OriginalValue = $this->Value;
		}

		/*
			Function: update
				Updates the setting properties and saves back to the database.

			Parameters:
				id - New ID for the setting
				type - Field Type
				settings - Field Type settings
				name - Name
				description - Description (HTML)
				locked - Whether the setting is locked to developers (truthy) or not (falsey)
				encrypted - Whether the setting's value should be encrypted in the database (truthy) or not (falsey)
				system - Whether the setting should be hidden from the Settings panel (truthy) or not (falsey)

			Returns:
				true if successful, false if a setting exists for the new id already.
		*/

		function update($id,$type = "",$settings = array(),$name = "",$description = "",$locked = "",$encrypted = "",$system = "") {
			// See if we have an id collision with the new id.
			if ($this->ID != $id && static::exists($id)) {
				return false;
			}

			$this->ID = $id;
			$this->Type = $type;
			$this->Settings = $settings;
			$this->Name = $name;
			$this->Description = $description;
			$this->Locked = $locked ? true : false;
			$this->Encrypted = $encrypted ? true : false;
			$this->System = $system ? true : false;

			$this->save();

			return true;
		}

		/*
			Function: values
				Gets the value of one or more settings.
				Can also be called by BigTree\Setting::value
			
			Parameters:
				ids - The ID of the setting or an array of setting IDs.
			
			Returns:				
				Either an array of setting values or a single setting's value.
		*/

		static function value($id) { return static::values($id); }
		static function values($ids) {
			global $bigtree;

			// Allow for a single ID
			if (!is_array($ids)) {
				$ids = array($ids);
			}

			$setting_values = array();

			foreach ($ids as $id) {
				$contextual_id = static::context($id);
				$setting = SQL::fetch("SELECT value, encrypted FROM bigtree_settings WHERE id = ?", $contextual_id);
				
				if ($setting) {
					// If the setting is encrypted, we need to re-pull just the value.
					if ($setting["encrypted"]) {
						$setting["value"] = SQL::fetchSingle("SELECT AES_DECRYPT(`value`, ?) FROM bigtree_settings WHERE id = ?",
															  $bigtree["config"]["settings_key"], $contextual_id);
					}
		
					$value = json_decode($setting["value"],true);
					
					if (is_array($value)) {
						$setting_values[$id] = BigTree::untranslateArray($value);
					} else {
						$setting_values[$id] = Link::decode($value);
					}
				}
			}

			// Allow for single ID
			if (count($ids) == 1) {
				return $setting_values[$ids[0]];
			}

			return $setting_values;
		}
	}
