<?php
	/*
		Class: BigTree\Setting
			Provides an interface for handling BigTree settings.
	*/
	
	namespace BigTree;
	
	class Setting extends JSONObject
	{
		
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
		
		public static $Store = "settings";
		
		/*
			Constructor:
				Builds a Setting object referencing an existing database entry.

			Parameters:
				setting - Either an ID (to pull a record) or an array (to use the array as the record)
				decode - Whether to decode the setting's value (defaults true, set to false for faster processing of large data value)
		*/
		
		public function __construct($setting = null, bool $decode = true)
		{
			if ($setting !== null) {
				// Passing in just an ID
				if (!is_array($setting)) {
					$id = static::context($setting);
					$setting = DB::get("settings", $id);
				}
				
				// Bad data set
				if (!is_array($setting)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->Description = $setting["description"];
					$this->Encrypted = $this->OriginalEncrypted = $setting["encrypted"] ? true : false;
					$this->Extension = $setting["extension"];
					$this->ID = $this->OriginalID = $setting["id"];
					$this->Locked = $setting["locked"] ? true : false;
					$this->Name = $setting["name"];
					$this->Settings = Link::decode(array_filter((array) $setting["settings"]));
					$this->System = $setting["system"] ? true : false;
					$this->Type = $setting["type"];
					
					// Value may be encrypted
					if ($this->Encrypted) {
						$value = SQL::fetchSingle("SELECT AES_DECRYPT(`value`,?) AS `value` FROM bigtree_settings 
												   WHERE id = ?", Router::$Config["settings_key"], $this->ID);
					} else {
						$value = $setting["value"];
					}
					
					// Decode value
					$value = json_decode($value, true);
					
					if ($decode) {
						if (is_string($value)) {
							$value = Link::decode($value);
						} elseif (is_array($value)) {
							$value = Link::decode($value);
						}
					}
					
					$this->Value = $this->OriginalValue = $value;
				}
			}
		}
		
		// Magic Method to allow usage of a Setting object as a string
		public function __toString(): string
		{
			if (is_object($this->Value) || is_array($this->Value)) {
				return JSON::encode($this->Value);
			} else {
				return strval($this->Value);
			}
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
		
		public static function context(string $id): string
		{
			global $bigtree;
			
			// See if we're in an extension
			if (!empty($bigtree["extension_context"])) {
				$extension = $bigtree["extension_context"];
				
				// If we're already asking for it by it's namespaced name, don't append again.
				if (substr($id, 0, strlen($extension)) == $extension) {
					return $id;
				}
				
				// See if namespaced version exists
				$exists = DB::exists("settings", $extension."*".$id) ||
						  SQL::exists("bigtree_settings", $extension."*".$id);
				
				if ($exists) {
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
				A Setting object if successful, otherwise null.
		*/
		
		public static function create(string $id, string $name = "", string $description = "", string $type = "",
									  array $settings = [], string $extension = "", bool $system = false,
									  bool $encrypted = false, bool $locked = false): ?Setting
		{
			// If an extension is creating a setting, make it a reference back to the extension
			if (defined("EXTENSION_ROOT") && !$extension) {
				$extension = rtrim(str_replace(SERVER_ROOT."extensions/", "", EXTENSION_ROOT), "/");
				
				// Don't append extension again if it's already being called via the namespace
				if (strpos($id, "$extension*") === false) {
					$id = "$extension*$id";
				}
			}
			
			if (strpos($id, "bigtree-internal-") === 0) {
				return null;
			}
			
			// Check for ID collision
			if (DB::exists("settings", $id) || SQL::exists("bigtree_settings", $id)) {
				return null;
			}
			
			// Create the setting
			DB::insert("settings", [
				"id" => $id,
				"name" => Text::htmlEncode($name),
				"description" => $description,
				"type" => Text::htmlEncode($type),
				"settings" => Link::encode(Utils::arrayFilterRecursive((array) $settings)),
				"locked" => $locked ? "on" : "",
				"encrypted" => $encrypted ? "on" : "",
				"system" => $system ? "on" : "",
				"extension" => $extension ? $extension : null
			]);
			SQL::insert("bigtree_settings", ["id" => $id, "encrypted" => $encrypted ? "on" : "", "value" => ""]);
			
			
			AuditTrail::track("config:settings", $id, "created");
			
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
		
		public static function exists(?string $id): bool
		{
			return DB::exists("settings", static::context($id));
		}
		
		/*
			Function: save
				Saves the current object properties back to the database.
		*/
		
		public function save(): ?bool
		{
			// Settings specify their own ID so we check for existance to determine save behavior
			if (!DB::exists("settings", $this->ID)) {
				$new = static::create(
					$this->ID,
					$this->Name,
					$this->Description,
					$this->Type,
					$this->Settings,
					$this->Extension,
					$this->System,
					$this->Encrypted,
					$this->Locked
				);
				
				$this->inherit($new);
			} else {
				DB::update("settings", $this->OriginalID, [
					"id" => $this->ID,
					"type" => $this->Type,
					"settings" => Link::encode(Utils::arrayFilterRecursive((array) $this->Settings)),
					"name" => Text::htmlEncode($this->Name),
					"description" => $this->Description,
					"locked" => $this->Locked ? "on" : "",
					"system" => $this->System ? "on" : "",
					"encrypted" => $this->Encrypted ? "on" : ""
				]);
				
				// If value has changed, set it now
				if ($this->Value != $this->OriginalValue) {
					// Do encoding
					if (is_array($this->Value)) {
						$value = Link::decode($this->Value);
					} else {
						$value = Link::encode($this->Value);
					}
					
					// Settings always expect JSON encoded value
					$value = json_encode($value);
					
					if ($this->Encrypted) {
						SQL::query("UPDATE bigtree_settings SET `value` = AES_ENCRYPT(?,?) WHERE id = ?",
								   $value, Router::$Config["settings_key"], $this->ID);
					} else {
						SQL::update("bigtree_settings", $this->ID, ["value" => $value]);
					}
					
					// If encryption status has changed, update the value directly
				} else {
					if ($this->OriginalEncrypted && !$this->Encrypted) {
						SQL::query("UPDATE bigtree_settings SET value = AES_DECRYPT(value, ?) WHERE id = ?",
								   Router::$Config["settings_key"], $this->ID);
					} elseif (!$this->OriginalEncrypted && $this->Encrypted) {
						SQL::query("UPDATE bigtree_settings SET value = AES_ENCRYPT(value, ?) WHERE id = ?",
								   Router::$Config["settings_key"], $this->ID);
					}
				}
				
				// If we changed IDs, leave a notice in the audit trail
				if ($this->OriginalID != $this->ID) {
					AuditTrail::track("config:settings", $this->OriginalID, "changed-id");
				}
				
				AuditTrail::track("config:settings", $this->ID, "updated");
				
				// Update "original" value tracking
				$this->OriginalEncrypted = $this->Encrypted;
				$this->OriginalID = $this->ID;
				$this->OriginalValue = $this->Value;
			}
			
			return true;
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
		
		public function update(string $id, string $type = "", array $settings = [], string $name = "",
							   string $description = "", bool $locked = false, bool $encrypted = false,
							   bool $system = false): bool
		{
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
			Function: updateValue
				Updates the value of an internal BigTree setting.

			Parameters:
				id - The id of the setting to update.
				value - A value to set (can be a string or array).
				encrypted - Whether the value should be encrypted (defaults to false).
		*/
		
		public static function updateValue(string $id, $value, bool $encrypted = false): void
		{
			$value = JSON::encode(Link::encode($value));
			
			if (!SQL::exists("bigtree_settings", $id)) {
				SQL::insert("bigtree_settings", [
					"id" => $id,
					"encrypted" => $encrypted ? "on" : ""
				]);
			}
			
			if ($encrypted) {
				SQL::query("UPDATE bigtree_settings SET `value` = AES_ENCRYPT(?, ?), `encrypted` = 'on'
							WHERE id = ?", $value, Router::$Config["settings_key"], $id);
			} else {
				SQL::update("bigtree_settings", $id, ["value" => $value, "encrypted" => ""]);
			}
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
		
		public static function value(string $id)
		{
			$values = static::values([$id]);
			
			return is_array($values) ? array_pop($values) : null;
		}
		
		public static function values(array $ids): ?array
		{
			// Allow for a single ID
			if (!is_array($ids)) {
				$ids = [$ids];
			}
			
			$setting_values = [];
			
			foreach ($ids as $id) {
				$contextual_id = static::context($id);
				$setting = SQL::fetch("SELECT value, encrypted FROM bigtree_settings WHERE id = ?", $contextual_id);
				
				if ($setting) {
					// If the setting is encrypted, we need to re-pull just the value.
					if ($setting["encrypted"]) {
						$setting["value"] = SQL::fetchSingle("SELECT AES_DECRYPT(`value`, ?) FROM bigtree_settings
															  WHERE id = ?",
															 Router::$Config["settings_key"], $contextual_id);
					}
					
					$value = json_decode($setting["value"], true);
					
					if (is_array($value)) {
						$setting_values[$id] = Link::decode($value);
					} else {
						$setting_values[$id] = Link::decode($value);
					}
				}
			}
			
			// Allow for failed lookup
			if (!count($setting_values)) {
				return null;
			}
			
			return $setting_values;
		}
		
	}
