<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Sanitize;
	use BigTree;
	use BigTreeAdmin;
	use BigTreeAutoModule;
	use BigTreeCMS;
	use BigTreeModule;
	use SQL;

	/**
	 * Site Integrity — the broken link/image checker from the legacy dashboard
	 * (core/admin/modules/dashboard/vitals-statistics/integrity). It walks every
	 * page and every auto-module entry, inspecting their HTML/text/callout/matrix
	 * fields for dead internal links, broken internal-page/resource links (ipl://
	 * irl://), missing local images, and — optionally — dead external links.
	 *
	 * The scan is incremental: the SPA builds a work list once (POST /start), then
	 * checks one page or one module entry per request (POST /check-page,
	 * /check-module-item). Progress + discovered errors are cached so a long scan
	 * can be resumed if the browser is closed mid-run, exactly like the legacy
	 * admin. External link checks are slow and prone to false positives, so they
	 * are opt-in.
	 *
	 * Cache lives under a dedicated identifier (kept separate from the legacy
	 * admin's so the two never collide) keyed by the scan mode (internal/external).
	 */
	class IntegrityService {
		const CACHE = "org.bigtreecms.integritycheck.spa";

		/**
		 * GET /dashboard/integrity/state
		 *
		 * Reports whether a resumable scan session exists for each mode so the SPA
		 * can offer "Resume" vs. a fresh start.
		 */
		public function state(Request $request) {

			return Response::ok([
				"internal_session" => (bool)BigTreeCMS::cacheGet(self::CACHE, "session.internal"),
				"external_session" => (bool)BigTreeCMS::cacheGet(self::CACHE, "session.external"),
			]);
		}

		/**
		 * POST /dashboard/integrity/start  { external: bool }
		 *
		 * Builds (or resumes) a scan session and returns the full work list — the
		 * page IDs and the module forms with their entry IDs — plus the current
		 * progress pointers and any errors already discovered. The SPA drives the
		 * scan client-side from this payload.
		 */
		public function start(Request $request) {
			$external = !empty($request->body["external"]) && $request->body["external"] !== "false";
			$key = $external ? "external" : "internal";
			$session_key = "session.".$key;
			$existing = BigTreeCMS::cacheGet(self::CACHE, $session_key);

			if ($existing) {
				$resumed = true;
				$pages = $existing["pages"];
				$modules = array_values($existing["modules"]);
				$current_page = (int)(BigTreeCMS::cacheGet(self::CACHE, "current_page.".$key) ?: 0);
				$current_module = (int)(BigTreeCMS::cacheGet(self::CACHE, "current_module.".$key) ?: 0);
				$current_item = (int)(BigTreeCMS::cacheGet(self::CACHE, "current_module_item.".$key) ?: 0);
				[$page_errors_raw, $module_errors] = $this->collectErrors($key);
				$page_errors = [];

				// Enrich resumed page errors with their nav titles so the SPA can
				// render them without an extra round-trip (live check-page results
				// already carry the title).
				foreach ($page_errors_raw as $id => $errs) {
					$nav_title = SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = ?", $id);
					$page_errors[$id] = [
						"nav_title" => Sanitize::decodeEntities($nav_title),
						"errors" => $errs,
					];
				}
			} else {
				// No session — wipe any stale state and build a fresh work list.
				BigTreeCMS::cacheDelete(self::CACHE);

				$resumed = false;
				$pages = BigTreeAdmin::getPageIds();
				$modules = $this->buildModuleList();
				$current_page = 0;
				$current_module = 0;
				$current_item = 0;
				$page_errors = [];
				$module_errors = [];

				BigTreeCMS::cachePut(self::CACHE, $session_key, [
					"pages" => $pages,
					"modules" => $modules,
				]);
			}

			return Response::ok([
				"external" => $external,
				"resumed" => $resumed,
				"pages" => array_map("intval", $pages),
				"modules" => $modules,
				"current_page" => $current_page,
				"current_module" => $current_module,
				"current_item" => $current_item,
				"page_errors" => $page_errors,
				"module_errors" => $module_errors,
			]);
		}

		/**
		 * POST /dashboard/integrity/check-page  { external: bool, id: int, index: int }
		 *
		 * Checks a single page's content fields and returns any broken links/images
		 * found. Stores them (and the progress pointer) for resume/export.
		 */
		public function checkPage(Request $request) {
			$external = !empty($request->body["external"]) && $request->body["external"] !== "false";
			$key = $external ? "external" : "internal";
			$id = (int)$request->body["id"];
			$index = $request->bodyInt("index");

			$cms = new BigTreeCMS();
			$page = $cms->getPage($id);
			$errors = [];

			if ($page && empty($page["external"])) {
				$template = $cms->getTemplate($page["template"]);
				$local_path = $cms->getLink($id);
				$resources = BigTree::translateArray($page["resources"]);

				if (!empty($template["resources"]) && is_array($template["resources"])) {
					$this->checkData($local_path, $external, $template["resources"], $resources, $errors);
				}
			}

			$flat = $this->flatten($errors);

			if ($flat) {
				BigTreeCMS::cachePut(self::CACHE, "errors.$key.pages.$id", $flat);
			}

			BigTreeCMS::cachePut(self::CACHE, "current_page.".$key, $index);

			return Response::ok([
				"id" => $id,
				"nav_title" => $page ? Sanitize::decodeEntities($page["nav_title"]) : "",
				"errors" => $flat,
			]);
		}

		/**
		 * POST /dashboard/integrity/check-module-item
		 *   { external: bool, form: string, id: int, module: int, index: int }
		 *
		 * Checks a single auto-module entry's content fields.
		 */
		public function checkModuleItem(Request $request) {
			$external = !empty($request->body["external"]) && $request->body["external"] !== "false";
			$key = $external ? "external" : "internal";
			$form_id = (string)$request->body["form"];
			$id = $request->body["id"];
			$module_index = $request->bodyInt("module");
			$index = $request->bodyInt("index");

			$form = BigTreeAutoModule::getForm($form_id);
			$errors = [];

			if ($form) {
				$m = new BigTreeModule();
				$m->Table = $form["table"];
				$item = BigTree::translateArray($m->get($id));

				if (is_array($form["fields"])) {
					$this->checkData("", $external, $form["fields"], $item, $errors);
				}
			}

			$flat = $this->flatten($errors);

			if ($flat) {
				BigTreeCMS::cachePut(self::CACHE, "errors.$key.modules.$form_id.$id", $flat);
			}

			BigTreeCMS::cachePut(self::CACHE, "current_module.".$key, $module_index);
			BigTreeCMS::cachePut(self::CACHE, "current_module_item.".$key, $index);

			return Response::ok([
				"form" => $form_id,
				"id" => $id,
				"errors" => $flat,
			]);
		}

		/**
		 * POST /dashboard/integrity/reset
		 *
		 * Wipes all scan session state (both modes).
		 */
		public function reset(Request $request) {
			BigTreeCMS::cacheDelete(self::CACHE);

			return Response::noContent();
		}

		/**
		 * GET /dashboard/integrity/export?external=true|false
		 *
		 * Flat dump of every error found in the requested mode's session, ready for
		 * the SPA to serialize to CSV client-side (mirrors the 404 export pattern).
		 */
		public function export(Request $request) {
			$external = ($request->query["external"] ?? "false") === "true";
			$key = $external ? "external" : "internal";
			$session = BigTreeCMS::cacheGet(self::CACHE, "session.".$key);
			[$page_errors, $module_errors] = $this->collectErrors($key);
			$rows = [];

			foreach ($page_errors as $id => $errors) {
				$nav_title = SQL::fetchSingle("SELECT nav_title FROM bigtree_pages WHERE id = ?", $id);

				foreach ($errors as $error) {
					$rows[] = [
						"location" => "Page",
						"title" => Sanitize::decodeEntities($nav_title),
						"type" => $error["type"],
						"url" => $error["url"],
						"field" => Sanitize::decodeEntities($error["field"]),
					];
				}
			}

			$module_lookup = [];

			if (!empty($session["modules"])) {
				foreach ($session["modules"] as $module) {
					$module_lookup[$module["id"]] = $module["name"];
				}
			}

			foreach ($module_errors as $form_id => $items) {
				foreach ($items as $errors) {
					foreach ($errors as $error) {
						$rows[] = [
							"location" => "Module",
							"title" => Sanitize::decodeEntities(($module_lookup[$form_id] ?? "")),
							"type" => $error["type"],
							"url" => $error["url"],
							"field" => Sanitize::decodeEntities($error["field"]),
						];
					}
				}
			}

			return Response::ok($rows);
		}

		// — internals —

		/**
		 * Builds the module work list: every module form that has an edit action,
		 * each annotated with a human-readable breadcrumb name, the IDs needed to
		 * build SPA edit links, and the list of entry IDs to scan. Honors a single
		 * matching view's filter so we don't scan entries the module hides.
		 *
		 * Ported from the legacy integrity/check.php session-build loop.
		 */
		private function buildModuleList(): array {
			$forms = BigTreeAdmin::getModuleForms();
			$list = [];

			foreach ($forms as $form) {
				$action = BigTreeAdmin::getModuleActionForForm($form);

				if (!$action) {
					continue;
				}

				$module = BigTreeAdmin::getModule($action["module"]);

				if (!$module) {
					continue;
				}

				// If exactly one view targets the form's table and that view has a
				// filter callback, apply it so we only scan visible entries.
				$view_match_count = 0;
				$filter = null;
				$edit_view_id = null;

				if (!empty($module["views"])) {
					foreach ($module["views"] as $view) {
						if ($view["table"] == $form["table"]) {
							$view_match_count++;
							$edit_view_id = $edit_view_id ?? $view["id"];

							if (!empty($view["settings"]["filter"])) {
								$filter = $view["settings"]["filter"];
							}
						}
					}

					// Fall back to the first view if none matched the form table.
					if ($edit_view_id === null) {
						$edit_view_id = $module["views"][0]["id"] ?? null;
					}
				}

				$group = !empty($module["group"]) ? BigTreeAdmin::getModuleGroup($module["group"]) : null;

				if (!empty($group["name"])) {
					$name = "Modules › ".$group["name"]." › ".$module["name"]." › ".$form["title"];
				} else {
					$name = "Modules › ".$module["name"]." › ".$form["title"];
				}

				$items = [];

				if ($view_match_count === 1 && $filter && is_callable($filter)) {
					$query = SQL::query("SELECT * FROM `".$form["table"]."`");

					while ($entry = $query->fetch()) {
						if (call_user_func($filter, $entry)) {
							$items[] = $entry["id"];
						}
					}
				} else {
					$items = SQL::fetchAllSingle("SELECT id FROM `".$form["table"]."`");
				}

				$list[] = [
					"id" => $form["id"],
					"name" => Sanitize::decodeEntities($name),
					"module_id" => $module["id"],
					"edit_view_id" => $edit_view_id,
					"items" => array_values($items),
				];
			}

			return $list;
		}

		/**
		 * Reads back every cached page/module error for a scan mode. Returns
		 * [page_errors, module_errors] where page_errors is keyed by page id and
		 * module_errors is keyed by form id then entry id, each holding the flat
		 * error list produced by flatten().
		 */
		private function collectErrors(string $key): array {
			$page_errors = [];
			$module_errors = [];

			$page_query = SQL::query(
				"SELECT `key`, `value` FROM bigtree_caches
				 WHERE `identifier` = ? AND `key` LIKE ?",
				self::CACHE, "errors.$key.pages.%"
			);

			while ($row = $page_query->fetch()) {
				$value = json_decode($row["value"], true);
				$id = str_replace("errors.$key.pages.", "", $row["key"]);

				if (!empty($value)) {
					$page_errors[$id] = $value;
				}
			}

			$module_query = SQL::query(
				"SELECT `key`, `value` FROM bigtree_caches
				 WHERE `identifier` = ? AND `key` LIKE ?",
				self::CACHE, "errors.$key.modules.%"
			);

			while ($row = $module_query->fetch()) {
				$value = json_decode($row["value"], true);
				$suffix = str_replace("errors.$key.modules.", "", $row["key"]);
				[$form_id, $item_id] = explode(".", $suffix, 2);

				if (!empty($value)) {
					if (!isset($module_errors[$form_id])) {
						$module_errors[$form_id] = [];
					}

					$module_errors[$form_id][$item_id] = $value;
				}
			}

			return [$page_errors, $module_errors];
		}

		/**
		 * Recursively checks a set of resource/field definitions against a decoded
		 * data set, accumulating broken links/images into $errors keyed by field
		 * title. Handles text (raw URLs), html (via BigTreeAdmin::checkHTML), and
		 * nested callouts/matrix columns.
		 *
		 * Ported from core/admin/ajax/dashboard/integrity-check/_header.php.
		 */
		private function checkData($local_path, bool $external, array $resources, $data_set, array &$errors): void {
			$data_set = is_array($data_set) ? $data_set : [];

			foreach ($resources as $resource_id => $resource) {
				if (!is_array($resource)) {
					continue;
				}

				$field = $resource["title"] ?? $resource_id;
				$data = $data_set[!empty($resource["id"]) ? $resource["id"] : $resource_id] ?? null;
				$type = $resource["type"] ?? "";

				if ($type == "text" && is_string($data)) {
					// External link.
					if (substr($data, 0, 4) == "http" && strpos($data, WWW_ROOT) === false) {
						if ($external) {
							// Strip hashes — they confuse urlExists.
							if (strpos($data, "#") !== false) {
								$data = substr($data, 0, strpos($data, "#") - 1);
							}

							if (!BigTreeAdmin::urlExists($data)) {
								$errors[$field] = ["a" => [$data]];
							}
						}
					} elseif (substr($data, 0, 4) == "http") {
						// Internal hard link.
						if ($data != WWW_ROOT && $data != STATIC_ROOT && $data != ADMIN_ROOT && !BigTreeAdmin::urlExists($data)) {
							$errors[$field] = ["a" => [$data]];
						}
					}
				} elseif ($type == "html") {
					$found = BigTreeAdmin::checkHTML($local_path, $data, $external);

					if (!empty($found)) {
						$errors[$field] = $found;
					}
				} elseif ($type == "callouts" && is_array($data)) {
					foreach ($data as $callout_data) {
						$callout = BigTreeAdmin::getCallout($callout_data["type"] ?? "");

						if ($callout) {
							$callout_resources = array_filter((array)$callout["resources"]);

							foreach ($callout_resources as &$column) {
								if (!empty($callout_data["display_title"])) {
									$column["title"] = $field." » ".$callout_data["display_title"]." » ".($column["title"] ?? "");
								} else {
									$column["title"] = $field." » ".($column["title"] ?? "");
								}
							}

							unset($column);

							$this->checkData($local_path, $external, $callout_resources, $callout_data, $errors);
						}
					}
				} elseif ($type == "matrix" && is_array($data)) {
					foreach ($data as $matrix_data) {
						if (isset($resource["settings"]) && is_string($resource["settings"])) {
							$resource["settings"] = json_decode($resource["settings"], true);
						} elseif (isset($resource["options"]) && is_string($resource["options"])) {
							$resource["options"] = json_decode($resource["options"], true);
						}

						$columns = array_filter((array)(!empty($resource["settings"]) ? $resource["settings"]["columns"] : ($resource["options"]["columns"] ?? [])));

						foreach ($columns as &$column) {
							if (!empty($matrix_data["__internal-title"])) {
								$column["title"] = $field." » ".$matrix_data["__internal-title"]." » ".($column["title"] ?? "");
							} else {
								$column["title"] = $field." » ".($column["title"] ?? "");
							}
						}

						unset($column);

						$this->checkData($local_path, $external, $columns, $matrix_data, $errors);
					}
				}
			}
		}

		/**
		 * Flattens the nested `field => [type => [urls]]` error structure into a
		 * simple list of `{ type, field, url }` objects the SPA can render and
		 * export directly. "img" is normalized to "image".
		 */
		private function flatten(array $errors): array {
			$out = [];

			foreach ($errors as $field => $types) {
				if (!is_array($types)) {
					continue;
				}

				foreach ($types as $type => $urls) {
					foreach ((array)$urls as $url) {
						$out[] = [
							"type" => $type === "img" ? "image" : "link",
							"field" => (string)$field,
							"url" => (string)$url,
						];
					}
				}
			}

			return $out;
		}
	}
