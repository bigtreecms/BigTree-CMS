<?php
	namespace BigTree\Services;

	use BigTreeCMS;
	use BigTree;
	use SQL;
	use BigTreeJSONDB;
	use BigTreeAutoModule;

	/**
	 * Resource allocation/tracking. Moved from legacy admin base class (cluster E).
	 */
	class ResourceAllocationService {

		public static $IRLPrefixes = [];
		public static $IRLsCreated = [];
		private static $ResourceByFileCache = [];
		private static $ResourceByUrlCache = [];
		private static $ResourceFileCachePrimed = false;

		public static function trackResource($resource) {
			$resource = intval($resource);

			if ($resource > 0) {
				self::$IRLsCreated[] = $resource;
			}
		}

		public static function getResourceUrlPrefixes() {
			global $bigtree;

			static $prefixes = null;

			if ($prefixes !== null) {
				return $prefixes;
			}

			$roots = [];
			$add_root = function($root) use (&$roots) {
				if (!is_string($root) || $root === "") {
					return;
				}

				if (substr($root, 0, 7) !== "http://" && substr($root, 0, 8) !== "https://" && substr($root, 0, 2) !== "//") {
					return;
				}

				$roots[] = rtrim($root, "/")."/";
			};

			$add_root(WWW_ROOT);
			$add_root(STATIC_ROOT);

			if (!empty($bigtree["config"]["sites"]) && is_array($bigtree["config"]["sites"])) {
				foreach ($bigtree["config"]["sites"] as $site) {
					$add_root($site["www_root"] ?? "");
					$add_root($site["static_root"] ?? "");
				}
			}

			BigTreeCMS::generateReplaceableRoots();

			foreach (BigTreeCMS::$ReplaceableRoots as $hard_root => $token) {
				$add_root($hard_root);
			}

			$prefixes = array_values(array_unique($roots));
			usort($prefixes, function($a, $b) {
				return strlen($b) - strlen($a);
			});

			return $prefixes;
		}

		public static function getKnownResourcePrefixes() {
			static $prefixes = null;

			if ($prefixes !== null) {
				return $prefixes;
			}

			$found = [];
			$add_prefix_list = function($list) use (&$found, &$add_prefix_list) {
				if (!is_array($list)) {
					return;
				}

				foreach ($list as $entry) {
					if (!is_array($entry)) {
						continue;
					}

					if (!empty($entry["prefix"])) {
						$found[] = $entry["prefix"];
					}

					$add_prefix_list($entry["thumbs"] ?? null);
					$add_prefix_list($entry["center_crops"] ?? null);
				}
			};

			$settings = BigTreeJSONDB::get("config", "media-settings");

			if (!empty($settings["presets"]) && is_array($settings["presets"])) {
				foreach ($settings["presets"] as $preset) {
					$add_prefix_list($preset["crops"] ?? null);
					$add_prefix_list($preset["thumbs"] ?? null);
					$add_prefix_list($preset["center_crops"] ?? null);
				}
			}

			// Resources keep the prefixes they were generated with, even if media settings have since changed
			foreach (SQL::fetchAll("SELECT DISTINCT crops, thumbs FROM bigtree_resources WHERE crops != '' OR thumbs != ''") as $row) {
				foreach ([$row["crops"], $row["thumbs"]] as $encoded) {
					$data = $encoded ? json_decode($encoded, true) : null;

					if (!is_array($data)) {
						continue;
					}

					foreach ($data as $prefix => $dimensions) {
						if (is_string($prefix) && $prefix !== "") {
							$found[] = $prefix;
						}
					}
				}
			}

			$prefixes = array_values(array_unique(array_filter($found)));
			usort($prefixes, function($a, $b) {
				return strlen($b) - strlen($a);
			});

			return $prefixes;
		}

		public static function getResourcePathFromUrl($url) {
			if (!is_string($url) || $url === "") {
				return false;
			}

			if (strpos($url, "files/resources/") === 0) {
				return $url;
			}

			$path = parse_url($url, PHP_URL_PATH);

			if (is_string($path)) {
				$path = ltrim($path, "/");

				if (strpos($path, "files/resources/") === 0) {
					return $path;
				}
			}

			foreach (static::getResourceUrlPrefixes() as $prefix) {
				if (strpos($url, $prefix) === 0) {
					$remainder = ltrim(substr($url, strlen($prefix)), "/");

					if (strpos($remainder, "files/resources/") === 0) {
						return $remainder;
					}
				}
			}

			return false;
		}

		public static function getResourceByUrl($url) {
			if (array_key_exists($url, self::$ResourceByUrlCache)) {
				return self::$ResourceByUrlCache[$url] ?: false;
			}

			$resource = self::getResourceByFile($url);

			if ($resource) {
				self::$ResourceByUrlCache[$url] = $resource;

				return $resource;
			}

			$path = static::getResourcePathFromUrl($url);

			if ($path) {
				$resource = self::getResourceByFile($path);

				if ($resource) {
					self::$ResourceByUrlCache[$url] = $resource;

					return $resource;
				}

				$resource = self::getResourceByFile(BigTreeCMS::replaceHardRoots($url));

				if ($resource) {
					self::$ResourceByUrlCache[$url] = $resource;

					return $resource;
				}
			}

			self::$ResourceByUrlCache[$url] = false;

			return false;
		}

		public static function primeResourceFileCache() {
			if (self::$ResourceFileCachePrimed) {
				return;
			}

			self::$ResourceFileCachePrimed = true;

			// Only ids are cached up front to keep memory low; lookups pull full rows on demand
			foreach (SQL::fetchAll("SELECT id, file FROM bigtree_resources") as $resource) {
				static::cacheResourceByFile($resource["file"], intval($resource["id"]));
			}
		}

		private static function cacheResourceByFile($file, $item) {
			$tokenized_file = BigTreeCMS::replaceHardRoots($file);
			$single_domain_tokenized_file = LinkService::stripMultipleRootTokens($tokenized_file);

			self::$ResourceByFileCache[$file] = $item;
			self::$ResourceByFileCache[$tokenized_file] = $item;
			self::$ResourceByFileCache[$single_domain_tokenized_file] = $item;
			self::$ResourceByFileCache[str_replace("{wwwroot}", "{staticroot}", $single_domain_tokenized_file)] = $item;
			self::$ResourceByFileCache[str_replace("{staticroot}", "{wwwroot}", $single_domain_tokenized_file)] = $item;
		}

		public static function trackResourcesInValue($value, $reference_keys = null) {
			$resources = [];
			static::collectResourcesInValue($value, $resources, $reference_keys, null);

			foreach ($resources as $resource) {
				static::trackResource($resource);
			}
		}

		public static function findResourcesInData($data, $reference_keys = null) {
			$resources = [];
			static::collectResourcesInValue($data, $resources, $reference_keys, null);

			return array_values(array_unique(array_filter(array_map("intval", $resources))));
		}

		private static function collectResourcesInValue($value, &$resources, $reference_keys, $current_key) {
			if (is_array($value)) {
				foreach ($value as $key => $piece) {
					static::collectResourcesInValue($piece, $resources, $reference_keys, $key);
				}
			} else {
				if ($reference_keys !== null && $current_key !== null && in_array($current_key, $reference_keys, true)) {
					if (is_numeric($value) && intval($value) > 0) {
						$resources[] = intval($value);
					}
				}

				if (is_string($value) && $value !== "") {
					if (preg_match_all("/irl:\/\/(\d+)/", $value, $matches)) {
						foreach ($matches[1] as $id) {
							$resources[] = intval($id);
						}
					}

					if (preg_match_all("/resource:\/\/([^\s\"']+)/", $value, $matches)) {
						foreach ($matches[1] as $file) {
							$resource = self::getResourceByFile($file);

							if ($resource) {
								$resources[] = intval($resource["id"]);
							}
						}
					}

					if (strpos($value, "files/resources/") !== false) {
						preg_match_all(
							"#(?:\{wwwroot(?::[^}]+)?\}|\{staticroot(?::[^}]+)?\}|https?://[^/\"'\\s]+|//[^/\"'\\s]+)?/?files/resources/[^\\s\"'<>]+#",
							$value,
							$matches
						);

						foreach ($matches[0] as $url) {
							$resource = static::getResourceByUrl($url);

							if ($resource) {
								$resources[] = intval($resource["id"]);
							}
						}
					}
				}
			}
		}

		public static function allocateResources($table, $entry) {
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);

			foreach (array_unique(self::$IRLsCreated) as $resource) {
				SQL::insert("bigtree_resource_allocation", [
					"table" => $table,
					"entry" => $entry,
					"resource" => $resource,
					"updated_at" => "NOW()"
				]);
			}

			self::$IRLsCreated = [];
		}

		public static function allocateResourcesFromData($table, $entry, $data, $reference_keys = null) {
			self::$IRLsCreated = [];
			static::trackResourcesInValue($data, $reference_keys);
			static::allocateResources($table, $entry);
		}

		public static function getResourceReferenceKeys($fields, $types = null) {
			if ($types === null) {
				$types = ["image-reference", "file-reference", "video-reference"];
			}

			$callout_group_cache = [];

			return static::walkResourceReferenceKeys($fields, $types, $callout_group_cache);
		}

		private static function walkResourceReferenceKeys($fields, $types, &$callout_group_cache) {
			global $admin;

			$keys = [];

			foreach ($fields as $field) {
				$type = $field["type"] ?? "";

				if (in_array($type, $types, true)) {
					$key = $field["column"] ?? $field["id"] ?? null;

					if ($key) {
						$keys[] = $key;
					}
				}

				if ($type === "callouts" && !empty($field["settings"]["callouts"])) {
					foreach ($field["settings"]["callouts"] as $callout) {
						$keys = array_merge($keys, static::walkResourceReferenceKeys($callout["fields"] ?? [], $types, $callout_group_cache));
					}
				}

				if ($type === "callouts" && !empty($field["settings"]["groups"])) {
					$group_key = implode(",", $field["settings"]["groups"]);

					if (!isset($callout_group_cache[$group_key])) {
						$callout_fields = [];
						$admin_instance = LegacyAdmin::bridge();

						foreach ($admin_instance->getCalloutsInGroups($field["settings"]["groups"], false) as $callout) {
							$callout_fields = array_merge($callout_fields, $callout["resources"] ?? []);
						}

						$callout_group_cache[$group_key] = static::walkResourceReferenceKeys($callout_fields, $types, $callout_group_cache);
					}

					$keys = array_merge($keys, $callout_group_cache[$group_key]);
				}

				if ($type === "matrix" && !empty($field["settings"]["columns"])) {
					$keys = array_merge($keys, static::walkResourceReferenceKeys($field["settings"]["columns"], $types, $callout_group_cache));
				}
			}

			return array_values(array_unique(array_filter($keys)));
		}

		public static function deallocateResources($table, $entry) {
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);
		}

		public static function getResourceAllocation($id) {
			return SQL::fetchAll("SELECT * FROM bigtree_resource_allocation WHERE resource = ? ORDER BY updated_at DESC", $id);
		}

		public static function getResourceAllocationUsage($id) {
			global $cms;

			$allocations = static::getResourceAllocation($id);
			$usages = [];
			$table_cache = [];

			foreach ($allocations as $allocation) {
				$table = $allocation["table"];
				$entry = strval($allocation["entry"]);
				$pending = (substr($entry, 0, 1) === "p");
				$usage = [
					"location" => $table,
					"title" => $entry,
					"edit_url" => null,
					"pending" => $pending,
					"archived" => false,
					"updated_at" => $allocation["updated_at"]
				];

				if ($table === "bigtree_pages") {
					$usage["location"] = "Pages";

					if ($pending) {
						$page = $cms->getPendingPage($entry, false);
						$usage["edit_url"] = ADMIN_ROOT."pages/edit/".$entry."/";
					} else {
						$page = $cms->getPage($entry, false);
						$usage["edit_url"] = ADMIN_ROOT."pages/edit/".$entry."/";
					}

					if ($page) {
						$usage["title"] = $page["nav_title"] ?: $page["title"];
						$usage["archived"] = !empty($page["archived"]) || !empty($page["archived_inherited"]);
					} else {
						$usage["title"] = "Deleted Page (".$entry.")";
						$usage["edit_url"] = null;
					}
				} elseif ($table === "bigtree_settings") {
					$setting = \BigTree\Services\SettingService::readSetting($entry);
					$usage["location"] = "Settings";

					if ($setting) {
						$usage["title"] = $setting["name"] ?: $setting["id"];

						if (empty($setting["system"])) {
							$usage["edit_url"] = ADMIN_ROOT."settings/edit/".$entry."/";
						}
					} else {
						$usage["title"] = "Deleted Setting (".$entry.")";
					}
				} else {
					if (!isset($table_cache[$table])) {
						$table_cache[$table] = static::getModuleEditInfoForTable($table);
					}

					$module_info = $table_cache[$table];
					$usage["location"] = $module_info["location"];

					if ($module_info["edit_url"]) {
						$usage["edit_url"] = $module_info["edit_url"].$entry."/";
					}

					$item = BigTreeAutoModule::getItem($table, $entry);

					if ($item) {
						$usage["title"] = static::getModuleEntryTitle($item["item"], $entry);
					} else {
						$usage["title"] = "Deleted Entry (".$entry.")";
						$usage["edit_url"] = null;
					}
				}

				$usages[] = $usage;
			}

			return $usages;
		}

		private static function getModuleEditInfoForTable($table) {
			$view = BigTreeAutoModule::getViewForTable($table);
			$module_id = BigTreeAutoModule::getModuleForView($view["id"]);
			$module = $module_id ? BigTreeJSONDB::get("modules", $module_id) : null;

			if (!$module) {
				return [
					"location" => $table,
					"edit_url" => null
				];
			}

			if ($view && !empty($view["edit_url"])) {
				return [
					"location" => $module["name"],
					"edit_url" => $view["edit_url"]
				];
			}

			foreach ($module["forms"] as $form) {
				if (($form["table"] ?? "") !== $table) {
					continue;
				}

				$action = \BigTree\Services\ModuleFormService::getModuleActionForForm($form);

				if ($module && $action) {
					return [
						"location" => $module["name"],
						"edit_url" => ADMIN_ROOT.$module["route"]."/".$action["route"]."/"
					];
				}
			}

			return [
				"location" => $table,
				"edit_url" => null
			];
		}

		private static function getModuleEntryTitle($item, $entry) {
			if (!is_array($item)) {
				return "Entry ".$entry;
			}

			foreach (["title", "name", "nav_title", "headline", "label"] as $key) {
				if (!empty($item[$key]) && is_string($item[$key])) {
					return strip_tags($item[$key]);
				}
			}

			if (!empty($item["id"])) {
				return "Entry ".$item["id"];
			}

			return "Entry ".$entry;
		}

		public static function updateResourceAllocation($table, $entry, $pending_id) {
			SQL::delete("bigtree_resource_allocation", ["table" => $table, "entry" => $entry]);
			SQL::update("bigtree_resource_allocation", ["table" => $table, "entry" => "p".$pending_id], ["entry" => $entry]);
		}
	
		public static function getResourceByFile($file) {
			if (array_key_exists($file, self::$ResourceByFileCache)) {
				$cached = self::$ResourceByFileCache[$file];

				// Primed id stubs fall through to a full lookup
				if (is_array($cached) || !$cached) {
					return $cached ?: false;
				}
			}

			if (!is_array(self::$IRLPrefixes) || !count(self::$IRLPrefixes)) {
				self::$IRLPrefixes = self::getKnownResourcePrefixes();
			}

			$last_prefix = false;
			$item = self::lookupResourceByFileCandidates($file, $already_decoded);

			// Try stripping thumbnail / crop prefixes to match the original file
			if (!$item) {
				foreach (self::$IRLPrefixes as $prefix) {
					$sfile = str_replace("files/resources/$prefix", "files/resources/", $file);

					if ($sfile === $file) {
						continue;
					}

					$item = self::lookupResourceByFileCandidates($sfile, $already_decoded);

					if ($item) {
						$last_prefix = $prefix;

						break;
					}
				}
			}

			if (!$item) {
				self::$ResourceByFileCache[$file] = false;

				return false;
			}

			// Items served from the cache have already been decoded
			if (!$already_decoded) {
				$item["prefix"] = false;
				$item["crops"] = !empty($item["crops"]) ? json_decode($item["crops"], true) : null;
				$item["thumbs"] = !empty($item["thumbs"]) ? json_decode($item["thumbs"], true) : null;
				$item["metadata"] = !empty($item["metadata"]) ? json_decode($item["metadata"], true) : null;
				$item["video_data"] = !empty($item["video_data"]) ? json_decode($item["video_data"], true) : null;
				$item = BigTree::untranslateArray($item);

				// Replace any primed id stubs for this resource with the decoded row
				self::cacheResourceByFile($item["file"], $item);
			}

			$item["prefix"] = $last_prefix;

			self::cacheResourceByFile($file, $item);

			return $item;
		}

		private static function lookupResourceByFileCandidates($file, &$already_decoded) {
			$already_decoded = false;
			$tokenized_file = BigTreeCMS::replaceHardRoots($file);
			$single_domain_tokenized_file = LinkService::stripMultipleRootTokens($tokenized_file);
			$candidates = array_values(array_unique([
				$file,
				$tokenized_file,
				$single_domain_tokenized_file,
				str_replace("{wwwroot}", "{staticroot}", $single_domain_tokenized_file),
				str_replace("{staticroot}", "{wwwroot}", $single_domain_tokenized_file)
			]));

			foreach ($candidates as $candidate) {
				if (!empty(self::$ResourceByFileCache[$candidate])) {
					$entry = self::$ResourceByFileCache[$candidate];

					if (is_array($entry)) {
						$already_decoded = true;

						return $entry;
					}

					// Primed entries only store the resource id; pull the full row on demand
					return SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $entry);
				}
			}

			// The primed cache contains every resource, so a miss is final
			if (self::$ResourceFileCachePrimed) {
				return false;
			}

			return SQL::fetch(
				"SELECT * FROM bigtree_resources WHERE file IN (".implode(", ", array_fill(0, count($candidates), "?")).")",
				...$candidates
			);
		}

		public static function getResource($id) {
			$resource = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", $id);

			if (!$resource) {
				return false;
			}

			$resource["crops"] = !empty($resource["crops"]) ? json_decode($resource["crops"], true) : null;
			$resource["thumbs"] = !empty($resource["thumbs"]) ? json_decode($resource["thumbs"], true) : null;
			$resource["metadata"] = !empty($resource["metadata"]) ? json_decode($resource["metadata"], true) : null;
			$resource["video_data"] = !empty($resource["video_data"]) ? json_decode($resource["video_data"], true) : null;

			return BigTree::untranslateArray($resource);
		}
	}
