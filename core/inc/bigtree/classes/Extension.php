<?php
	/*
		Class: BigTree\Extension
			Provides an interface for handling BigTree extensions.
	*/
	
	namespace BigTree;
	
	class Extension extends JSONObject
	{
		protected $LastUpdated;
		
		public $ID;
		public $Manifest;
		public $Name;
		public $Type;
		public $Version;
		
		public static $CacheInitialized = false;
		public static $Hooks = [];
		public static $RequiredFiles = [];
		public static $Table = "extensions";
		
		/*
			Constructor:
				Builds a Extension object referencing an existing database entry.

			Parameters:
				extension - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($extension = null)
		{
			if ($extension !== null) {
				// Passing in just an ID
				if (!is_array($extension)) {
					$extension = DB::get("extensions", $extension);
				}
				
				// Bad data set
				if (!is_array($extension)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $extension["id"];
					$this->LastUpdated = $extension["last_updated"];
					$this->Manifest = array_filter((array) @json_decode($extension["manifest"], true));
					$this->Name = $extension["name"];
					$this->Type = $extension["type"];
					$this->Version = $extension["version"];
				}
			}
		}
		
		/*
			Function: cacheHooks
				Deletes an existing extension hooks cache and re-caches based on the latest data.
		*/
		
		public static function cacheHooks(): array
		{
			$plugins = [
				"cron" => [],
				"daily-digest" => [],
				"dashboard" => [],
				"interfaces" => [],
				"view-types" => [],
				"hooks" => []
			];
			
			$extensions = DB::getAll("extensions");
			
			foreach ($extensions as $extension) {
				// Load up the manifest
				$manifest = json_decode(file_get_contents(SERVER_ROOT."extensions/".$extension["id"]."/manifest.json"), true);
				
				if (!empty($manifest["plugins"]) && is_array($manifest["plugins"])) {
					foreach ($manifest["plugins"] as $type => $list) {
						foreach ($list as $id => $plugin) {
							$plugins[$type][$manifest["id"]][$id] = $plugin;
						}
					}
				}
				
				$hook_base_dir = SERVER_ROOT."extensions/".$extension["id"]."/hooks/";
				
				if (file_exists($hook_base_dir)) {
					$hook_files = FileSystem::getDirectoryContents($hook_base_dir, true, "php");
					
					foreach ($hook_files as $file) {
						$parts = explode("/", str_replace($hook_base_dir, "", substr($file, 0, -4)));
						
						if (count($parts) == 2) {
							$plugins["hooks"][$parts[0]][$parts[1]][] = str_replace(SERVER_ROOT, "", $file);
						} elseif (count($parts) == 1) {
							$plugins["hooks"][$parts[0]][] = str_replace(SERVER_ROOT, "", $file);
						}
					}
				}
			}
			
			// If no longer in debug mode, cache it
			if (!Router::$Debug) {
				file_put_contents(SERVER_ROOT."cache/bigtree-extension-cache.json", JSON::encode($plugins));
			}
			
			return $plugins;
		}
		
		/*
			Function: delete
				Uninstalls the extension or package from BigTree and removes its related components and files.
		*/
		
		public function delete(): ?bool
		{
			// Prevent the whole directory from being deleted if this doesn't have an ID
			if (empty($this->ID) || !DB::exists("extensions", $this->ID)) {
				return false;
			}
			
			FileSystem::deleteDirectory(SITE_ROOT."extensions/".$this->ID."/");
			FileSystem::deleteDirectory(SERVER_ROOT."extensions/".$this->ID."/");
			
			foreach ($this->Manifest["components"] as $type => $list) {
				if ($type == "tables") {
					// Drop all the tables the extension created
					SQL::query("SET SESSION foreign_key_checks = 0");
					
					foreach ($list as $table => $create_statement) {
						SQL::query("DROP TABLE IF EXISTS `$table`");
					}
					
					SQL::query("SET SESSION foreign_key_checks = 1");
				} else {
					// Remove other JSON config entries
					foreach ($list as $item) {
						DB::delete($type, $item["id"]);
					}
				}
			}
			
			DB::delete("extensions", $this->ID);
			AuditTrail::track("config:extensions", $this->ID, "delete", "deleted");
			
			return true;
		}
		
		/*
			Function: initializeCache
				Initializes any extension plugins and caches them to the proper objects.
		*/
		
		public static function initializeCache(): void
		{
			// Already done!
			if (static::$CacheInitialized) {
				return;
			}
			
			$extension_cache_file = SERVER_ROOT."cache/bigtree-extension-cache.json";
			
			// Handle extension cache
			if (Router::$Debug || !file_exists($extension_cache_file)) {
				$plugins = static::cacheHooks();
			} else {
				$plugins = json_decode(file_get_contents($extension_cache_file), true);
			}
			
			Cron::$Plugins = $plugins["cron"];
			DailyDigest::$Plugins = $plugins["daily-digest"];
			Dashboard::$Plugins = $plugins["dashboard"];
			ModuleInterface::$Plugins = $plugins["interfaces"];
			ModuleView::$Plugins = $plugins["view-types"];
			
			static::$Hooks = $plugins["hooks"];
			static::$CacheInitialized = true;
		}
		
		/*
			Function: installFromManifest
				Installs an extension from a manifest.

			Parameters:
				manifest - Manifest array
				upgrade - Old manifest array (if doing an upgrade, otherwise leave null)

			Returns:
				An Extension object.
		*/
		
		public static function installFromManifest(array $manifest, ?array $upgrade = null): ?Extension
		{
			$group_match = $module_match = $class_name_match = $form_id_match = $view_id_match = $report_id_match = [];
			$extension = $manifest["id"];
			
			// Turn off foreign key checks so we can reference the extension before creating it
			SQL::query("SET foreign_key_checks = 0");
			
			// Upgrades drop existing modules, templates, etc -- we don't drop settings because they have user data
			if (is_array($upgrade)) {
				$modules = DB::getAll("modules");
				
				foreach ($modules as $item) {
					if ($item["extension"] == $extension) {
						DB::delete("modules", $item["id"]);
					}
				}
				
				$module_groups = DB::getAll("module-groups");
				
				foreach ($module_groups as $group) {
					if ($group["extension"] == $extension) {
						DB::delete("module-groups", $group["id"]);
					}
				}
				
				$templates = DB::getAll("templates");
				
				foreach ($templates as $item) {
					if ($item["extension"] == $extension) {
						DB::delete("templates", $item["id"]);
					}
				}
				
				$callouts = DB::getAll("callouts");
				
				foreach ($callouts as $item) {
					if ($item["extension"] == $extension) {
						DB::delete("callouts", $item["id"]);
					}
				}
				
				$field_types = DB::getAll("field-types");
				
				foreach ($field_types as $item) {
					if ($item["extension"] == $extension) {
						DB::delete("field-types", $item["id"]);
					}
				}
				
				$feeds = DB::getAll("feeds");
				
				foreach ($feeds as $item) {
					if ($item["extension"] == $extension) {
						DB::delete("feeds", $item["id"]);
					}
				}
			// Import tables for new installs
			} else {
				foreach ($manifest["components"]["tables"] as $table_name => $sql_statement) {
					SQL::query("DROP TABLE IF EXISTS `$table_name`");
					SQL::query($sql_statement);
				}
			}
			
			// Import module groups
			foreach ($manifest["components"]["module_groups"] as &$group) {
				if ($group) {
					$group_match[$group["id"]] = ModuleGroup::create($group["name"]);
					
					// Update the group ID since we're going to save this manifest locally for uninstalling
					$group["id"] = $group_match[$group["id"]];
					DB::update("module-groups", $group["id"], ["extension" => $extension]);
				}
			}
			
			// Import modules
			foreach ($manifest["components"]["modules"] as &$module) {
				if ($module) {
					$group = isset($group_match[$module["group"]]) ? $group_match[$module["group"]] : null;
					$module_id = DB::insert("modules", [
						"name" => $module["name"],
						"route" => $module["route"],
						"class" => $module["class"],
						"icon" => $module["icon"],
						"group" => $group,
						"gbp" => $module["gbp"],
						"extension" => $extension
					]);
					
					$module_match[$module["id"]] = $module_id;
					
					// Update the module ID since we're going to save this manifest locally for uninstalling
					$module["id"] = $module_id;
					
					// Create views
					foreach ($module["views"] as $view) {
						$view = ModuleView::create($module_id, $view["title"], $view["description"], $view["table"],
												   $view["type"], $view["settings"], $view["fields"], $view["actions"],
												   $view["suffix"], $view["preview_url"]);
						$view_id_match[$view["id"]] = $view->ID;
					}
					
					// Create regular forms
					foreach ($module["forms"] as $form) {
						$return_view = isset($view_id_match[$form["return_view"]]) ? $view_id_match[$form["return_view"]] : null;
						$form = ModuleForm::create($module_id, $form["title"], $form["table"], $form["fields"],
												   $form["hooks"], $form["default_position"], $return_view,
												   $form["return_url"], !empty($form["tagging"]));
						$form_id_match[$form["id"]] = $form->ID;
					}
					
					// Create reports
					foreach ($module["reports"] as $report) {
						$view = isset($view_id_match[$report["view"]]) ? $view_id_match[$report["view"]] : null;
						$report = ModuleReport::create($module_id, $report["title"], $report["table"], $report["type"],
													   $report["filters"], $report["fields"], $report["parser"], $view);
						$report_id_match[$report["id"]] = $report->ID;
					}
					
					// Create actions
					foreach ($module["actions"] as $action) {
						$form = isset($form_id_match[$action["form"]]) ? $form_id_match[$action["form"]] : null;
						$view = isset($view_id_match[$action["view"]]) ? $view_id_match[$action["view"]] : null;
						$report = isset($report_id_match[$action["report"]]) ? $report_id_match[$action["report"]] : null;
						
						ModuleAction::create($module_id, $action["name"], $action["route"], $action["in_nav"],
											 $action["class"], $form, $view, $report, $action["level"],
											 $action["position"]);
					}
					
					// Update related form state for views
					foreach ($module["views"] as $view) {
						if ($view["related_form"]) {
							$context = DB::getSubset("modules", $module_id);
							$context->update("views", $view_id_match[$view["id"]], [
								"related_form" => $form_id_match[$view["related_form"]]
							]);
						}
					}
				}
			}
			
			// Import templates
			foreach ($manifest["components"]["templates"] as $template) {
				if ($template) {
					DB::insert("templates", [
						"id" => $template["id"],
						"name" => $template["name"],
						"module" => $module_match[$template["module"]],
						"resources" => $template["resources"],
						"level" => $template["level"],
						"routed" => $template["routed"],
						"extension" => $extension
					]);
				}
			}
			
			// Import callouts
			foreach ($manifest["components"]["callouts"] as $callout) {
				if ($callout) {
					DB::insert("callouts", [
						"id" => $callout["id"],
						"name" => $callout["name"],
						"description" => $callout["description"],
						"display_default" => $callout["display_default"],
						"display_field" => $callout["display_field"],
						"resources" => $callout["resources"],
						"level" => $callout["level"],
						"position" => $callout["position"],
						"extension" => $extension
					]);
				}
			}
			
			// Import Settings
			foreach ($manifest["components"]["settings"] as $setting) {
				if ($setting) {
					Setting::create($setting["id"], $setting["name"], $setting["description"], $setting["type"],
									$setting["settings"], $extension, $setting["system"], $setting["encrypted"],
									$setting["locked"]);
				}
			}
			
			// Import Feeds
			foreach ($manifest["components"]["feeds"] as &$feed) {
				if ($feed) {
					$feed["id"] = DB::insert("feeds", [
						"route" => $feed["route"],
						"name" => $feed["name"],
						"description" => $feed["description"],
						"type" => $feed["type"],
						"table" => $feed["table"],
						"fields" => $feed["fields"],
						"settings" => $feed["settings"],
						"extension" => $extension
					]);
				}
			}
			
			// Import Field Types
			foreach ($manifest["components"]["field_types"] as $type) {
				if ($type) {
					DB::insert("field-types", [
						"id" => $type["id"],
						"name" => $type["name"],
						"use_cases" => $type["use_cases"],
						"self_draw" => $type["self_draw"],
						"extension" => $extension
					]);
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
							SQL::query($sql_statement);
						}
					}
				}
				
				// Update the extension
				DB::update("extensions", $manifest["id"], [
					"name" => $manifest["title"],
					"version" => $manifest["version"],
					"last_updated" => date("Y-m-d H:i:s"),
					"manifest" => $manifest
				]);
				
			// Straight installs move files into place locally
			} else {
				// Make sure destination doesn't exist
				$destination_path = SERVER_ROOT."extensions/".$manifest["id"]."/";
				FileSystem::deleteDirectory($destination_path);
				
				// Move the package to the extension directory
				rename(SERVER_ROOT."cache/package/",$destination_path);
				FileSystem::setDirectoryPermissions($destination_path);
				
				// Create the extension
				DB::insert("extensions", [
					"id" => $manifest["id"],
					"name" => $manifest["title"],
					"version" => $manifest["version"],
					"last_updated" => date("Y-m-d H:i:s"),
					"manifest" => $manifest
				]);
			}
			
			// Re-enable foreign key checks
			SQL::query("SET foreign_key_checks = 1");
			
			// Empty view cache
			SQL::query("DELETE FROM bigtree_module_view_cache");
			
			// Move public files into the site directory
			$public_dir = SERVER_ROOT."extensions/".$manifest["id"]."/public/";
			$site_contents = file_exists($public_dir) ? BigTree::directoryContents($public_dir) : [];
			
			foreach ($site_contents as $file_path) {
				$destination_path = str_replace($public_dir,SITE_ROOT."extensions/".$manifest["id"]."/",$file_path);
				FileSystem::copyFile($file_path,$destination_path);
			}
			
			// Clear module class cache and field type cache.
			@unlink(SERVER_ROOT."cache/bigtree-module-class-list.json");
			@unlink(SERVER_ROOT."cache/bigtree-form-field-types.json");
			
			static::cacheHooks();
			
			return new Extension($extension);
		}
		
		/*
			Function: runHooks
				Runs extension hooks of a given type for a given context.

			Parameters:
				type - Hook type
				context - Hook context
				data - Data to modify (will be returned modified)
				data_context - Additional data context (will be global variables in the context of the hook, not returned)

			Returns:
				Data modified by hook script
		*/
		
		public static function runHooks($type, $context = "", $data = "", $data_context = []) {
			if (!static::$CacheInitialized) {
				static::initializeCache();
			}
			
			// Anonymous function so that hooks can't pollute context
			$run_hook = function($hook, $data, $data_context = []) {
				foreach ($data_context as $key => $value) {
					$$key = $value;
				}
				
				include SERVER_ROOT.$hook;
				return $data;
			};
			
			if ($context) {
				if (!empty(static::$Hooks[$type][$context]) && is_array(static::$Hooks[$type][$context])) {
					foreach (static::$Hooks[$type][$context] as $hook) {
						$data = $run_hook($hook, $data, $data_context);
					}
				}
			} else {
				if (!empty(static::$Hooks[$type]) && is_array(static::$Hooks[$type])) {
					foreach (static::$Hooks[$type] as $hook) {
						$data = $run_hook($hook, $data, $data_context);
					}
				}
			}
			
			return $data;
		}
		
	}
