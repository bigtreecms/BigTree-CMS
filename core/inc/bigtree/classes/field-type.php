<?php
	/*
		Class: BigTree\FieldType
			Provides an interface for handling BigTree field types.
	*/

	namespace BigTree;

	use BigTreeCMS;

	class FieldType {

		/*
			Function: reference
				Caches available field types and returns a reference array for them.

			Parameters:
				split - Whether to split the field types into separate default / custom arrays (defaults to false)

			Returns:
				Array of three arrays of field types (template, module, and callout).
		*/

		static function reference($split = false) {
			// Used cached values if available, otherwise query the DB
			if (file_exists(SERVER_ROOT."cache/bigtree-form-field-types.json")) {
				$types = json_decode(file_get_contents(SERVER_ROOT."cache/bigtree-form-field-types.json"),true);
			} else {
				$types = array();
				$types["modules"] = $types["templates"] = $types["callouts"] = $types["settings"] = array(
					"default" => array(
						"text" => array("name" => "Text", "self_draw" => false),
						"textarea" => array("name" => "Text Area", "self_draw" => false),
						"html" => array("name" => "HTML Area", "self_draw" => false),
						"upload" => array("name" => "Upload", "self_draw" => false),
						"list" => array("name" => "List", "self_draw" => false),
						"checkbox" => array("name" => "Checkbox", "self_draw" => false),
						"date" => array("name" => "Date Picker", "self_draw" => false),
						"time" => array("name" => "Time Picker", "self_draw" => false),
						"datetime" => array("name" => "Date &amp; Time Picker", "self_draw" => false),
						"photo-gallery" => array("name" => "Photo Gallery", "self_draw" => false),
						"callouts" => array("name" => "Callouts", "self_draw" => true),
						"matrix" => array("name" => "Matrix", "self_draw" => true),
						"one-to-many" => array("name" => "One to Many", "self_draw" => false)
					),
					"custom" => array()
				);

				$types["modules"]["default"]["route"] = array("name" => "Generated Route","self_draw" => true);

				$field_types = BigTreeCMS::$DB->fetchAll("SELECT * FROM bigtree_field_types ORDER BY name");
				foreach ($field_types as $field_type) {
					$use_cases = json_decode($field_type["use_cases"],true);
					foreach ((array)$use_cases as $case => $val) {
						if ($val) {
							$types[$case]["custom"][$field_type["id"]] = array("name" => $field_type["name"],"self_draw" => $field_type["self_draw"]);
						}
					}
				}

				BigTree::putFile(SERVER_ROOT."cache/bigtree-form-field-types.json",BigTree::json($types));
			}

			// Re-merge if we don't want them split
			if (!$split) {
				foreach ($types as $use_case => $list) {
					$types[$use_case] = array_merge($list["default"],$list["custom"]);
				}
			}

			return $types;
		}

	}
