<?php
	/*
		Class: BigTree\Callout
			Provides an interface for handling BigTree callouts.
	*/

	namespace BigTree;

	use BigTreeCMS;

	class Callout {

		/*
			Function: all
				Returns a list of callouts.

			Parameters:
				sort - The order to return the callouts. Defaults to positioned.

			Returns:
				An array of callout entries from bigtree_callouts.
		*/

		static function all($sort = "position DESC, id ASC") {
			return BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_callouts ORDER BY $sort");
		}

		/*
			Function: allAllowed
				Returns a list of callouts the logged-in user is allowed access to.

			Parameters:
				sort - The order to return the callouts. Defaults to positioned.

			Returns:
				An array of callout entries from bigtree_callouts.
		*/

		static function allAllowed($sort = "position DESC, id ASC") {
			global $admin;

			return BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_callouts WHERE level <= ? ORDER BY $sort", $admin->Level);
		}

		/*
			Function: allInGroups
				Returns a list of callouts in a given set of groups.

			Parameters:
				groups - An array of group IDs to retrieve callouts for.
				auth - If set to true, only returns callouts the logged in user has access to. Defaults to true.

			Returns:
				An alphabetized array of entries from the bigtree_callouts table.
		*/

		static function allInGroups($groups,$auth = true) {
			global $admin;
			$ids = $callouts = $names = array();

			foreach ($groups as $group_id) {
				$group = new BigTree\CalloutGroup($group_id);

				foreach ($group["callouts"] as $callout_id) {
					// Only grab each callout once
					if (!in_array($callout_id,$ids)) {
						$callout = static::get($callout_id);
						$ids[] = $callout_id;

						// If we're looking at only the ones the user is allowed to access, check levels
						if (!$auth || $admin->Level >= $callout["level"]) {
							$callouts[] = $callout;
							$names[] = $callout["name"];
						}
					}
				}
			}
			
			array_multisort($names,$callouts);
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
				resources - An array of resources.
				display_field - The field to use as the display field describing a user's callout
				display_default - The text string to use in the event the display_field is blank or non-existent

			Returns:
				true if successful, false if an invalid ID was passed or the ID is already in use
		*/

		static function create($id,$name,$description,$level,$resources,$display_field,$display_default) {
			// Check to see if it's a valid ID
			if (!ctype_alnum(str_replace(array("-","_"),"",$id)) || strlen($id) > 127) {
				return false;
			}

			// See if a callout ID already exists
			if (BigTreeCMS::$DB->exists("bigtree_callouts",$id)) {
				return false;
			}

			// If we're creating a new file, let's populate it with some convenience things to show what resources are available.
			$file_contents = '<?php
	/*
		Resources Available:
';

			$cached_types = BigTree\FieldType::reference();
			$types = $cached_types["callouts"];

			$clean_resources = array();
			foreach ($resources as $resource) {
				// "type" is still a reserved keyword due to the way we save callout data when editing.
				if ($resource["id"] && $resource["id"] != "type") {
					$field = array(
						"id" => BigTree::safeEncode($resource["id"]),
						"type" => BigTree::safeEncode($resource["type"]),
						"title" => BigTree::safeEncode($resource["title"]),
						"subtitle" => BigTree::safeEncode($resource["subtitle"]),
						"options" => (array)@json_decode($resource["options"],true)
					);

					// Backwards compatibility with BigTree 4.1 package imports
					foreach ($resource as $k => $v) {
						if (!in_array($k,array("id","title","subtitle","type","options"))) {
							$field["options"][$k] = $v;
						}
					}

					$clean_resources[] = $field;

					$file_contents .= '		"'.$resource["id"].'" = '.$resource["title"].' - '.$types[$resource["type"]]["name"]."\n";
				}
			}

			$file_contents .= '	*/
?>';

			// Create the template file if it doesn't yet exist
			if (!file_exists(SERVER_ROOT."templates/callouts/$id.php")) {
				BigTree::putFile(SERVER_ROOT."templates/callouts/$id.php",$file_contents);
			}

			// Increase the count of the positions on all templates by 1 so that this new template is for sure in last position.
			BigTreeCMS::$DB->query("UPDATE bigtree_callouts SET position = position + 1");

			// Insert the callout
			BigTreeCMS::$DB->insert("bigtree_callouts",array(
				"id" => BigTree::safeEncode($id),
				"name" => BigTree::safeEncode($name),
				"description" => BigTree::safeEncode($description),
				"resources" => $clean_resources,
				"level" => $level,
				"display_field" => $display_field,
				"display_default" => $display_default

			));

			BigTree\AuditTrail::track("bigtree_callouts",$id,"created");

			return $id;
		}

		/*
			Function: delete
				Deletes a callout and removes its file.

			Parameters:
				id - The id of the callout.
		*/

		static function delete($id) {
			// Delete template file
			unlink(SERVER_ROOT."templates/callouts/$id.php");

			// Delete callout
			BigTreeCMS::$DB->delete("bigtree_callouts",$id);

			// Remove the callout from any groups it lives in
			$groups = BigTreeCMS::$DB->fetchAll("SELECT id, callouts FROM bigtree_callout_groups 
												 WHERE callouts LIKE '%\"".BigTreeCMS::$DB->escape($id)."\"%'");
			foreach ($groups as $group) {
				$callouts = array_filter((array)json_decode($group["callouts"],true));
				// Remove this callout
				$callouts = array_diff($callouts, array($id));
				// Update DB
				BigTreeCMS::$DB->update("bigtree_callout_groups",$group["id"],array("callouts" => $callouts));
			}

			// Track deletion
			BigTree\AuditTrail::track("bigtree_callouts",$id,"deleted");
		}

		/*
			Function: get
				Returns a callout entry.

			Parameters:
				id - The id of the callout.

			Returns:
				A callout entry from bigtree_callouts with resources decoded.
		*/

		static function get($id) {
			$callout = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_callouts WHERE id = ?", $id);
			if (!$callout) {
				return false;
			}

			$callout["resources"] = json_decode($callout["resources"],true);
			return $callout;
		}

		/*
			Function: setPosition
				Sets the position of a callout.

			Parameters:
				id - The id of the callout.
				position - The position to set.
		*/

		static function setPosition($id,$position) {
			BigTreeCMS::$DB->update("bigtree_callouts",$id,array("position" => $position));
		}

		/*
			Function: update
				Updates a callout.

			Parameters:
				id - The id of the callout to update.
				name - The name.
				description - The description.
				level - The access level (0 for all users, 1 for administrators, 2 for developers)
				resources - An array of resources.
				display_field - The field to use as the display field describing a user's callout
				display_default - The text string to use in the event the display_field is blank or non-existent
		*/

		static function update($id,$name,$description,$level,$resources,$display_field,$display_default) {
			$clean_resources = array();
			foreach ($resources as $resource) {
				// "type" is still a reserved keyword due to the way we save callout data when editing.
				if ($resource["id"] && $resource["id"] != "type") {
					$clean_resources[] = array(
						"id" => BigTree::safeEncode($resource["id"]),
						"type" => BigTree::safeEncode($resource["type"]),
						"title" => BigTree::safeEncode($resource["title"]),
						"subtitle" => BigTree::safeEncode($resource["subtitle"]),
						"options" => json_decode($resource["options"],true)
					);
				}
			}

			BigTreeCMS::$DB->update("bigtree_callouts",$id,array(
				"resources" => $clean_resources,
				"name" => BigTree::safeEncode($name),
				"description" => BigTree::safeEncode($description),
				"level" => $level,
				"display_field" => $display_field,
				"display_default" => $display_default
			));

			BigTree\AuditTrail::track("bigtree_callouts",$id,"updated");
		}

	}
