<?php
	/*
		Class: BigTree\Extension
			Provides an interface for handling BigTree extensions.
	*/

	namespace BigTree;

	use BigTree;
	use BigTreeCMS;

	class Extension extends BaseObject {

		static $Table = "bigtree_extensions";

		protected $ID;
		protected $LastUpdated;

		public $Manifest;
		public $Name;
		public $Type;
		public $Version;

		/*
			Constructor:
				Builds a Extension object referencing an existing database entry.

			Parameters:
				extension - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($extension) {
			// Passing in just an ID
			if (!is_array($extension)) {
				$extension = BigTreeCMS::$DB->fetch("SELECT * FROM bigtree_extensions WHERE id = ?", $extension);
			}

			// Bad data set
			if (!is_array($extension)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_WARNING);
			} else {
				$this->ID = $extension["id"];
				$this->LastUpdated = $extension["last_updated"];

				$this->Manifest = array_filter((array) @json_decode($extension["manifest"],true));
				$this->Name = $extension["name"];
				$this->Type = $extension["type"];
				$this->Version = $extension["version"];
			}
		}

		/*
			Function: createFromManifest
				Installs an extension from a manifest.

			Parameters:
				manifest - Manifest array
				upgrade - Old manifest array (if doing an upgrade, otherwise leave false)

			Returns:
				An Extension object.
		*/

		function createFromManifest($manifest,$upgrade = false) {
			global $bigtree;

			// Initialize a bunch of empty arrays
			$bigtree["group_match"] = $bigtree["module_match"] = $bigtree["route_match"] = $bigtree["class_name_match"] = $bigtree["form_id_match"] = $bigtree["view_id_match"] = $bigtree["report_id_match"] = array();
			$extension = $manifest["id"];

			// Turn off foreign key checks so we can reference the extension before creating it
			BigTreeCMS::$DB->query("SET foreign_key_checks = 0");

			// Upgrades drop existing modules, templates, etc -- we don't drop settings because they have user data
			if (is_array($upgrade)) {
				BigTreeCMS::$DB->delete("bigtree_module_groups",array("extension" => $extension));
				BigTreeCMS::$DB->delete("bigtree_modules",array("extension" => $extension));
				BigTreeCMS::$DB->delete("bigtree_templates",array("extension" => $extension));
				BigTreeCMS::$DB->delete("bigtree_callouts",array("extension" => $extension));
				BigTreeCMS::$DB->delete("bigtree_field_types",array("extension" => $extension));
				BigTreeCMS::$DB->delete("bigtree_feeds",array("extension" => $extension));

			// Import tables for new installs
			} else { 
				foreach ($manifest["components"]["tables"] as $table_name => $sql_statement) {
					BigTreeCMS::$DB->query("DROP TABLE IF EXISTS `$table_name`");
					BigTreeCMS::$DB->query($sql_statement);
				}
			}

			// Import module groups
			foreach ($manifest["components"]["module_groups"] as &$group) {
				if (array_filter((array)$group)) {
					$bigtree["group_match"][$group["id"]] = $this->createModuleGroup($group["name"]);
					// Update the group ID since we're going to save this manifest locally for uninstalling
					$group["id"] = $bigtree["group_match"][$group["id"]];
					BigTreeCMS::$DB->update("bigtree_module_groups",$group["id"],arrayÏ("extension" => $extension));
				}
			}
		
			// Import modules
			foreach ($manifest["components"]["modules"] as &$module) {
				if (array_filter((array)$module)) {
					$group = ($module["group"] && isset($bigtree["group_match"][$module["group"]])) ? $bigtree["group_match"][$module["group"]] : null;
					
					// Find a unique route
					$route = BigTreeCMS::$DB->unique("bigtree_modules","route",$module["route"]);

					// Create the module
					$module_id = BigTreeCMS::$DB->insert("bigtree_modules",array(
						"name" => $module["name"],
						"route" => $route,
						"class" => $module["class"],
						"icon" => $module["icon"],
						"group" => $group,
						"gbp" => $module["gbp"],
						"extension" => $extension
					));

					// Setup matches
					$bigtree["module_match"][$module["id"]] = $module_id;
					$bigtree["route_match"][$module["route"]] = $route;

					// Update the module ID since we're going to save this manifest locally for uninstalling
					$module["id"] = $module_id;
			
					// Create the embed forms
					foreach ($module["embed_forms"] as $form) {
						$this->createModuleEmbedForm($module_id,$form["title"],$form["table"],BigTree::arrayValue($form["fields"]),$form["hooks"],$form["default_position"],$form["default_pending"],$form["css"],$form["redirect_url"],$form["thank_you_message"]);
					}

					// Create views
					foreach ($module["views"] as $view) {
						$view_object = View::create($module_id,$view["title"],$view["description"],$view["table"],$view["type"],BigTree::arrayValue($view["options"]),BigTree::arrayValue($view["fields"]),BigTree::arrayValue($view["actions"]),$view["suffix"],$view["preview_url"]);
						$bigtree["view_id_match"][$view["id"]] = $view_object->ID;
					}

					// Create regular forms
					foreach ($module["forms"] as $form) {
						$form_object = Form::create($module_id,$form["title"],$form["table"],BigTree::arrayValue($form["fields"]),$form["hooks"],$form["default_position"],($form["return_view"] ? $bigtree["view_id_match"][$form["return_view"]] : false),$form["return_url"],$form["tagging"]);
						$bigtree["form_id_match"][$form["id"]] = $form_object->ID;
					}

					// Create reports
					foreach ($module["reports"] as $report) {
						$report_object = Report::create($module_id,$report["title"],$report["table"],$report["type"],BigTree::arrayValue($report["filters"]),BigTree::arrayValue($report["fields"]),$report["parser"],($report["view"] ? $bigtree["view_id_match"][$report["view"]] : false));
						$bigtree["report_id_match"][$report["id"]] = $report_object->ID;
					}

					// Create actions
					foreach ($module["actions"] as $action) {
						// 4.1 and 4.2 compatibility
						if ($action["report"]) {
							$action["interface"] = $bigtree["report_id_match"][$action["report"]];
						} elseif ($action["form"]) {
							$action["interface"] = $bigtree["form_id_match"][$action["form"]];
						} elseif ($action["view"]) {
							$action["interface"] = $bigtree["view_id_match"][$action["view"]];
						}
						ModuleAction::create($module_id,$action["name"],$action["route"],$action["in_nav"],$action["class"],$action["interface"],$action["level"],$action["position"]);
					}
				}
			}
		
			// Import templates
			foreach ($manifest["components"]["templates"] as $template) {
				if (array_filter((array)$template)) {
					BigTreeCMS::$DB->insert("bigtree_templates",array(
						"id" => $template["id"],
						"name" => $template["name"],
						"module" => $bigtree["module_match"][$template["module"]],
						"resources" => $template["resources"],
						"level" => $template["level"],
						"routed" => $template["routed"],
						"extension" => $extension
					));
				}
			}
		
			// Import callouts
			foreach ($manifest["components"]["callouts"] as $callout) {
				if (array_filter((array)$callout)) {
					BigTreeCMS::$DB->insert("bigtree_callouts",array(
						"id" => $callout["id"],
						"name" => $callout["name"],
						"description" => $callout["description"],
						"display_default" => $callout["display_default"],
						"display_field" => $callout["display_field"],
						"resources" => $callout["resources"],
						"level" => $callout["level"],
						"position" => $callout["position"],
						"extension" => $extension
					));
				}
			}
		
			// Import Settings
			foreach ($manifest["components"]["settings"] as $setting) {
				if (array_filter((array)$setting)) {
					$this->createSetting($setting);
					BigTreeCMS::$DB->update("bigtree_settings",$setting["id"],array("extension" => $extension));
				}
			}
		
			// Import Feeds
			foreach ($manifest["components"]["feeds"] as $feed) {
				if (array_filter((array)$feed)) {
					BigTreeCMS::$DB->insert("bigtree_feeds",array(
						"route" => $feed["route"],
						"name" => $feed["name"],
						"description" => $feed["description"],
						"type" => $feed["type"],
						"table" => $feed["table"],
						"fields" => $feed["fields"],
						"options" => $feed["options"],
						"extension" => $extension
					));
				}
			}
		
			// Import Field Types
			foreach ($manifest["components"]["field_types"] as $type) {
				if (array_filter((array)$type)) {
					BigTreeCMS::$DB->insert("bigtree_field_types",array(
						"id" => $type["id"],
						"name" => $type["name"],
						"use_cases" => $type["use_cases"],
						"self_draw" => $type["self_draw"] ? "'on'" : null,
						"extension" => $extension
					));
				}
			}

			// Upgrades don't drop tables, we run the SQL revisions instead
			if (is_array($upgrade)) {
				$old_revision = $upgrade["revision"];
				$sql_revisions = $manifest["sql_revisions"];

				// Go through all the SQL updates, we ksort first to ensure if the manifest somehow got out of order that we run the SQL update sequentially
				ksort($sql_revisions);
				foreach ($sql_revisions as $key => $statements) {
					if ($key > $old_revision) {
						foreach ($statements as $sql_statement) {
							BigTreeCMS::$DB->query($sql_statement);
						}
					}
				}

				// Update the extension
				BigTreeCMS::$DB->update("bigtree_extensions",$extension,array(
					"name" => $manifest["title"],
					"version" => $manifest["version"],
					"manifest" => $manifest
				));
			
			// Straight installs move files into place locally
			} else {
				// Make sure destination doesn't exist
				$destination_path = SERVER_ROOT."extensions/".$manifest["id"]."/"; 
				BigTree::deleteDirectory($destination_path);

				// Move the package to the extension directory
				rename(SERVER_ROOT."cache/package/",$destination_path);
				BigTree::setDirectoryPermissions($destination_path);

				// Create the extension
				BigTreeCMS::$DB->insert("bigtree_extensions",array(
					"id" => $extension,
					"type" => "extension",
					"name" => $manifest["title"],
					"version" => $manifest["version"],
					"manifest" => $manifest
				));	
			}

			// Re-enable foreign key checks
			BigTreeCMS::$DB->query("SET foreign_key_checks = 1");

			// Empty view cache
			BigTreeCMS::$DB->query("DELETE FROM bigtree_module_view_cache");

			// Move public files into the site directory
			$public_dir = SERVER_ROOT."extensions/".$manifest["id"]."/public/";
			$site_contents = file_exists($public_dir) ? BigTree::directoryContents($public_dir) : array();
			foreach ($site_contents as $file_path) {
				$destination_path = str_replace($public_dir,SITE_ROOT."extensions/".$manifest["id"]."/",$file_path);
				BigTree::copyFile($file_path,$destination_path);
			}

			// Clear module class cache and field type cache.
			BigTree::deleteFile(SERVER_ROOT."cache/bigtree-module-cache.json");
			BigTree::deleteFile(SERVER_ROOT."cache/bigtree-form-field-types.json");

			return new Extension($manifest["id"]);
		}


		/*
			Function: deleteExtension
				Uninstalls the extension from BigTree and removes its related components and files.
		*/

		function delete() {
			// Delete site files
			BigTree::deleteDirectory(SITE_ROOT."extensions/".$this->ID."/");
			// Delete extensions directory
			BigTree::deleteDirectory(SERVER_ROOT."extensions/".$this->ID."/");
		
			// Delete components
			foreach ($this->Manifest["components"] as $type => $list) {
				if ($type == "tables") {
					// Turn off foreign key checks since we're going to be dropping tables.
					BigTreeCMS::$DB->query("SET SESSION foreign_key_checks = 0");
					
					// Drop all the tables the extension created
					foreach ($list as $table => $create_statement) {
						BigTreeCMS::$DB->query("DROP TABLE IF EXISTS `$table`");
					}
					
					// Re-enable foreign key checks
					BigTreeCMS::$DB->query("SET SESSION foreign_key_checks = 1");
				} else {
					// Remove other database entries
					foreach ($list as $item) {
						BigTreeCMS::$DB->delete("bigtree_".$type,$item["id"]);
					}
				}
			}

			// Delete extension entry
			BigTreeCMS::$DB->delete("bigtree_extensions",$this->ID);
			
			// Track
			AuditTrail::track("bigtree_extensions",$this->ID,"deleted");
		}

	}
