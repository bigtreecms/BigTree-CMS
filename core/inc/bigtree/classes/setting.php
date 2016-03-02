<?php
	/*
		Class: BigTree\Setting
			Provides an interface for handling BigTree settings.
	*/

	namespace BigTree;

	use BigTreeCMS;
	use BigTreeAdmin;

	class Setting {

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
				if (BigTreeCMS::$DB->exists("bigtree_settings",array("id" => "$extension*$id"))) {
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
				options - An array of options for the field
				extension - Related extension ID (defaults to none unless an extension is calling createSetting)
				system - Whether to hide this from the Settings tab (defaults to false)
				encrypted - Whether to encrypt this setting in the database (defaults to false)
				locked - Whether to lock this setting to only developers (defaults to false)

			Returns:
				True if successful, false if a setting already exists with the ID given.
		*/

		static function create($id,$name = "",$description = "",$type = "",$options = array(),$extension = "",$system = false,$encrypted = false,$locked = false) {
			global $admin;

			// Allow for backwards compatibility with pre-4.3 syntax
			if (is_array($id)) {
				$data = $id;
				$id = false;
				// Loop through and create our expected parameters.
				foreach ($data as $key => $val) {
					if (substr($key,0,1) != "_") {
						$$key = $val;
					}
				}
			}

			// No id, bad call.
			if (!$id) {
				return false;
			}
			
			// If an extension is creating a setting, make it a reference back to the extension
			if (defined("EXTENSION_ROOT") && !$extension) {
				$extension = rtrim(str_replace(SERVER_ROOT."extensions/","",EXTENSION_ROOT),"/");
				
				// Don't append extension again if it's already being called via the namespace
				if (strpos($id,"$extension*") === false) {
					$id = "$extension*$id";
				}
			}

			// Check for ID collision
			if (BigTreeCMS::$DB->exists("bigtree_settings",$id)) {
				return false;
			}

			// Create the setting
			BigTreeCMS::$DB->insert("bigtree_settings",array(
				"id" => $id,
				"name" => BigTree::safeEncode($name),
				"description" => $description,
				"type" => BigTree::safeEncode($type),
				"options" => array_filter((array)$options),
				"locked" => $locked ? "on" : "",
				"encrypted" => $encrypted ? "on" : "",
				"system" => $system ? "on" : "",
				"extension" => $extension ? $extension : null
			));

			BigTree\AuditTrail::track("bigtree_settings",$id,"created");

			return true;
		}

		/*
			Function: delete
				Deletes a setting.

			Parameters:
				id - The id of the setting.
		*/

		function delete($id) {
			// Grab extension based ID if we're calling from an extension
			$id = static::context($id);

			// Delete setting
			BigTreeCMS::$DB->delete("bigtree_settings",$id);
			BigTree\AuditTrail::track("bigtree_settings",$id,"deleted");
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
			return BigTreeCMS::$DB->exists("bigtree_settings",static::context($id));
		}

		/*
			Function: get
				Returns a setting.

			Parameters:
				id - The id of the setting to return.
				decode - Whether to decode the array or not. Large data sets may want to set this to false if there aren't internal page links.

			Returns:
				A setting entry with its value properly decoded and decrypted.
				Returns false if the setting could not be found.
		*/

		static function get($id,$decode = true) {
			global $bigtree;

			$id = static::context($id);
			$setting = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_settings WHERE id = ?", $id);
			
			// Setting doesn't exist
			if (!$setting) {
				return false;
			}

			// Encrypted setting
			if ($setting["encrypted"]) {
				$setting["value"] = BigTreeCMS::$DB->fetchSingle("SELECT AES_DECRYPT(`value`,?) AS `value` 
																  FROM bigtree_settings 
																  WHERE id = ?", $bigtree["config"]["settings_key"], $id);
			}

			// Decode the JSON value
			if ($decode) {
				$setting["value"] = json_decode($setting["value"],true);
	
				if (is_array($setting["value"])) {
					$setting["value"] = BigTree::untranslateArray($setting["value"]);
				} else {
					$setting["value"] = BigTree\Link::parseHTML($setting["value"]);
				}
			}

			return $setting;
		}

		/*
			Function: list
				Returns a list of all settings that the logged in user has access to.

			Parameters:
				sort - Order to return the settings. Defaults to name ASC.

			Returns:
				An array of entries from bigtree_settings.
				If the setting is encrypted the value will be "[Encrypted Text]", otherwise it will be decoded.
				If the calling user is a developer, returns locked settings, otherwise they are left out.
		*/

		function list($sort = "name ASC") {
			$lock_check = ($this->Level < 2) ? "locked = '' AND " : "";

			$settings = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_settings WHERE $lock_check system = '' ORDER BY $sort");
			foreach ($settings as &$setting) {
				if ($setting["encrypted"] == "on") {
					$setting["value"] = "[Encrypted Text]";
				} else {
					$setting["value"] = json_decode($setting["value"],true);
				}
				$setting = BigTree::untranslateArray($setting);
			}

			return $settings;
		}

		/*
			Function: update
				Updates a setting.

			Parameters:
				old_id - The current id of the setting to update.
				id - New ID for the setting
				type - Field Type
				options - Field Type options
				name - Name
				description - Description (HTML)
				locked - Whether the setting is locked to developers (truthy) or not (falsey)
				encrypted - Whether the setting's value should be encrypted in the database (truthy) or not (falsey)
				system - Whether the setting should be hidden from the Settings panel (truthy) or not (falsey)

			Returns:
				true if successful, false if a setting exists for the new id already.
		*/

		function update($old_id,$id,$type = "",$options = array(),$name = "",$description = "",$locked = "",$encrypted = "",$system = "") {
			global $bigtree;

			// Allow for pre-4.3 parameter syntax
			if (is_array($id)) {
				$data = $id;
				$id = "";

				foreach ($data as $key => $val) {
					if (substr($key,0,1) != "_") {
						$$key = $val;
					}
				}
			}

			// Get the existing setting information.
			$existing = static::get($old_id);

			// See if we have an id collision with the new id.
			if ($old_id != $id && static::exists($id)) {
				return false;
			}

			// Update base setting
			BigTreeCMS::$DB->update("bigtree_settings",$old_id,array(
				"id" => $id,
				"type" => $type,
				"options" => $options,
				"name" => BigTree::safeEncode($name),
				"description" => $description,
				"locked" => $locked ? "on" : "",
				"system" => $system ? "on" : "",
				"encrypted" => $encrypted ? "on" : ""
			));

			// If encryption status has changed, update the value
			if ($existing["encrypted"] && !$encrypted) {
				BigTreeeCMS::$DB->query("UPDATE bigtree_settings SET value = AES_DECRYPT(value, ?) WHERE id = ?", 
										 $bigtree["config"]["settings_key"], $id);
			} elseif (!$existing["encrypted"] && $encrypted) {
				BigTreeeCMS::$DB->query("UPDATE bigtree_settings SET value = AES_ENCRYPT(value, ?) WHERE id = ?", 
										 $bigtree["config"]["settings_key"], $id);
			}

			// Audit trail.
			BigTree\AuditTrail::track("bigtree_settings",$id,"updated");
			if ($id != $old_id) {
				BigTree\AuditTrail::track("bigtree_settings",$old_id,"changed-id");
			}

			return true;
		}

		/*
			Function: updateValue
				Updates the value of a setting.

			Parameters:
				id - The id of the setting to update.
				value - A value to set (can be a string or array).
		*/

		static function updateValue($id,$value) {
			global $bigtree;

			$id = static::context($id);
			$item = BigTreeCMS::$DB->fetch("SELECT encrypted, system FROM bigtree_settings WHERE id = ?", $id);

			// Do encoding
			if (is_array($value)) {
				$value = BigTree::translateArray($value);
			} else {
				$value = static::autoIPL($value);
			}

			if ($item["encrypted"]) {
				BigTreeCMS::$DB->query("UPDATE bigtree_settings SET `value` = AES_ENCRYPT(?,?) WHERE id = ?", 
										$value, $bigtree["config"]["settings_key"], $id);
			} else {
				BigTreeCMS::$DB->update("bigtree_settings",$id,array("value" => $value));
			}

			// Audit trail.
			if (!$item["system"]) {
				BigTree\AuditTrail::track("bigtree_settings",$id,"updated");
			}
		}

		/*
			Function: value
				Gets the value of one or more settings.
			
			Parameters:
				ids - The ID of the setting or an array of setting IDs.
			
			Returns:				
				Either an array of setting values or a single setting's value.
		*/
		
		static function value($ids) {
			global $bigtree;

			// Allow for a single ID
			if (!is_array($ids)) {
				$ids = array($ids);
			}

			$setting_values = array();

			foreach ($ids as $id) {
				$contextual_id = static::context($id);
				$setting = BigTreeCMS::$DB->fetch("SELECT value, encrypted FROM bigtree_settings WHERE id = ?", $contextual_id);
				
				if ($setting) {
					// If the setting is encrypted, we need to re-pull just the value.
					if ($setting["encrypted"]) {
						$setting["value"] = BigTreeCMS::$DB->fetchSingle("SELECT AES_DECRYPT(`value`, ?) FROM bigtree_settings WHERE id = ?",
																		  $bigtree["config"]["settings_key"], $contextual_id);
					}
		
					$value = json_decode($setting["value"],true);
					
					if (is_array($value)) {
						$setting_values[$id] = BigTree::untranslateArray($value);
					} else {
						$setting_values[$id] = BigTree\Link::parseHTML($value);
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
