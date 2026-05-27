<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeAdmin;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use BigTree;

	/**
	 * Modules + module groups. Sub-resources (forms, views, reports, actions) are
	 * exposed read-only in v1; their schemas are complex enough that creation/edit
	 * via API is a follow-on (the developer UI still creates them).
	 */
	class ModuleService {
		public function list(Request $request) {
			$rows = BigTreeJSONDB::getAll("modules", "position", "DESC");

			$me = $request->user;
			$visible = array_filter($rows, function ($m) use ($me) {

				return PermissionService::userHasModuleAccess($me, $m["id"], "v");
			});

			$enriched = array_map(function ($m) {
				$group_name = "";

				if (!empty($m["group"])) {
					$g = BigTreeJSONDB::get("module-groups", $m["group"]);

					if ($g) {
						$group_name = $g["name"];
					}
				}

				return $this->present($m, $group_name);
			}, array_values($visible));

			return Response::ok($enriched);
		}

		public function get(Request $request) {
			$id = $request->route_params["id"];
			$m = BigTreeJSONDB::get("modules", $id);

			if (!$m) {
				throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			}

			if (!PermissionService::userHasModuleAccess($request->user, $id, "v")) {
				throw new \BigTree\Api\Exceptions\AuthorizationException("Module access denied", "permission_denied", 403);
			}

			$group_name = "";

			if (!empty($m["group"])) {
				$g = BigTreeJSONDB::get("module-groups", $m["group"]);

				if ($g) {
					$group_name = $g["name"];
				}
			}

			return Response::ok($this->present($m, $group_name));
		}

		public function create(Request $request) {
			$d = $request->body;
			$route = $d["route"] ?? BigTreeCMS::urlify($d["name"]);

			if (!ctype_alnum(str_replace("-", "", $route)) || strlen($route) > 127) {
				throw new BadRequestException("Module route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route", 400);
			}

			$route = $this->uniqueModuleRoute($route);
			$id = BigTreeJSONDB::insert("modules", [
				"name" => BigTree::safeEncode($d["name"]),
				"group" => $d["group"] ?? null,
				"class" => $d["class"] ?? "",
				"table" => $d["table"] ?? "",
				"gbp" => $d["gbp"] ?? ["enabled" => false],
				"icon" => $d["icon"] ?? "",
				"route" => $route,
				"graphql" => !empty($d["graphql"]) ? "on" : "",
				"graphql_type" => $d["graphql_type"] ?? "",
				"position" => 0,
			]);

			return Response::created($this->present(BigTreeJSONDB::get("modules", $id)), null);
		}

		public function update(Request $request) {
			$id = $request->route_params["id"];
			$existing = BigTreeJSONDB::get("modules", $id);

			if (!$existing) {
				throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			}
			$d = $request->body;

			$next = array_merge($existing, array_filter([
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : null,
				"group" => array_key_exists("group", $d) ? $d["group"] : null,
				"class" => $d["class"] ?? null,
				"gbp" => $d["gbp"] ?? null,
				"icon" => $d["icon"] ?? null,
				"graphql" => array_key_exists("graphql", $d) ? (!empty($d["graphql"]) ? "on" : "") : null,
				"graphql_type" => $d["graphql_type"] ?? null,
			], function ($v) { return $v !== null; }));

			BigTreeJSONDB::update("modules", $id, $next);

			return Response::ok($this->present(BigTreeJSONDB::get("modules", $id)));
		}

		public function delete(Request $request) {
			$id = $request->route_params["id"];

			if (!BigTreeJSONDB::exists("modules", $id)) {
				throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			}

			BigTreeJSONDB::delete("modules", $id);

			// Cascade: cleanup module-forms / module-views / module-reports / module-actions / module-embed-forms
			foreach (["module-forms", "module-views", "module-reports", "module-actions", "module-embed-forms"] as $sub) {
				$entries = BigTreeJSONDB::getAll($sub);

				foreach ($entries as $e) {
					if (($e["module"] ?? "") == $id) {
						BigTreeJSONDB::delete($sub, $e["id"]);
					}
				}
			}

			return Response::noContent();
		}

		public function reorder(Request $request) {
			$ids = array_map("intval", (array)$request->body["ids"]);
			$pos = count($ids);

			foreach ($ids as $id) {
				if (BigTreeJSONDB::exists("modules", $id)) {
					BigTreeJSONDB::update("modules", $id, ["position" => $pos--]);
				}
			}

			return Response::noContent();
		}

		// — sub-resources (actions / forms / views / reports / embed-forms) —
		// Storage note: these are nested arrays inside each module's JSON document,
		// not separate JSONDB types. We read via the module record and write via
		// BigTreeJSONDB::getSubset which scopes writes to the module's subtree.

		public function actions(Request $request) {
			$module = $this->loadModule($request->route_params["id"]);

			return Response::ok($this->sortByPosition($module["actions"] ?? []));
		}

		public function forms(Request $request) {
			$module = $this->loadModule($request->route_params["id"]);

			return Response::ok(array_values($module["forms"] ?? []));
		}

		public function views(Request $request) {
			$module = $this->loadModule($request->route_params["id"]);

			return Response::ok(array_values($module["views"] ?? []));
		}

		public function reports(Request $request) {
			$module = $this->loadModule($request->route_params["id"]);

			return Response::ok(array_values($module["reports"] ?? []));
		}

		public function embedForms(Request $request) {
			$module = $this->loadModule($request->route_params["id"]);

			return Response::ok(array_values($module["embed-forms"] ?? []));
		}

		/**
		 * Resolve {id, title} options for a one-to-many or many-to-many form
		 * field. Driven by the field's stored `settings` so the SPA does not
		 * need to know — or be allowed to name — the underlying tables.
		 *
		 *   GET /modules/{id}/forms/{sid}/relation-options?column=&q=&ids=&entry=
		 *
		 * Modes:
		 *   - default — return candidate options (filtered by ?q, capped at 250).
		 *   - ?ids=   — return only the items whose id is in the comma list
		 *               (used to fetch human titles for a known selection).
		 *   - ?entry= — many-to-many only: return the items currently linked to
		 *               entry N via the connecting table, in stored order.
		 *
		 * Returns:
		 *   {
		 *     items:    [{id, title}, ...],
		 *     relation: { type: "one-to-many"|"many-to-many", sortable: bool }
		 *   }
		 *
		 * `sortable` only applies to MTM and reflects whether the connecting
		 * table has a `position` column (matches the legacy draw.php behavior
		 * that toggles drag-reorder availability on the field).
		 */
		public function relationOptions(Request $request) {
			$module_id = $request->route_params["id"];
			$form_id = $request->route_params["sid"];
			$column = (string)($request->query["column"] ?? "");

			if ($column === "") {
				throw new BadRequestException("`column` query param is required", "missing_column", 400);
			}

			$module = $this->loadModule($module_id);
			$form = $this->findSub($module["forms"] ?? [], $form_id);

			if (!$form) {
				throw new NotFoundException("Form $form_id not found", "resource_not_found", 404);
			}

			$field = null;

			foreach ((array)($form["fields"] ?? []) as $candidate) {
				if (($candidate["column"] ?? "") === $column) {
					$field = $candidate;

					break;
				}
			}

			if (!$field) {
				throw new NotFoundException("Field `$column` not found in form $form_id", "resource_not_found", 404);
			}

			$type = (string)($field["type"] ?? "");

			if ($type !== "one-to-many" && $type !== "many-to-many") {
				throw new BadRequestException(
					"Field `$column` is type `$type`, not a relation field",
					"invalid_field_type",
					400
				);
			}

			$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

			$conn_table = "";

			if ($type === "one-to-many") {
				$table = (string)($settings["table"] ?? "");
				$descriptor = (string)($settings["title_column"] ?? "");
				$sort = (string)($settings["sort_by_column"] ?? "");
				$sortable = false;
			} else {
				$table = (string)($settings["mtm-other-table"] ?? "");
				$descriptor = (string)($settings["mtm-other-descriptor"] ?? "");
				$sort = (string)($settings["mtm-sort"] ?? "");
				$conn_table = (string)($settings["mtm-connecting-table"] ?? "");
				$sortable = false;

				if ($conn_table !== "") {
					$conn = BigTree::describeTable($conn_table);

					if ($conn && !empty($conn["columns"]["position"])) {
						$sortable = true;
					}
				}
			}

			if ($table === "" || $descriptor === "") {
				throw new BadRequestException(
					"Field `$column` is missing table/descriptor settings",
					"invalid_field_settings",
					400
				);
			}

			$schema = BigTree::describeTable($table);

			if (!$schema || empty($schema["columns"])) {
				throw new NotFoundException("Table `$table` not found", "resource_not_found", 404);
			}

			if (empty($schema["columns"][$descriptor])) {
				throw new BadRequestException(
					"Descriptor column `$descriptor` does not exist on `$table`",
					"invalid_field_settings",
					400
				);
			}

			// Sort clause — validate against the column list so we never
			// concatenate user-controlled text into the SQL. Default to the
			// descriptor when the configured sort references a missing column.
			$order_by = $this->safeOrderClause($sort, $schema["columns"], $descriptor);

			$entry_raw = trim((string)($request->query["entry"] ?? ""));

			// "Currently linked" mode (MTM only). Query the connecting table to
			// discover which `other-id`s the entry already has — preserves the
			// stored position when the connecting table is sortable.
			if ($entry_raw !== "") {
				if ($type !== "many-to-many") {
					throw new BadRequestException(
						"`?entry=` is only valid for many-to-many fields",
						"invalid_request",
						400
					);
				}

				if (!ctype_digit($entry_raw)) {
					throw new BadRequestException("`entry` must be a positive integer", "invalid_request", 400);
				}

				$entry_id = (int)$entry_raw;
				$my_id_col = (string)($settings["mtm-my-id"] ?? "");
				$other_id_col = (string)($settings["mtm-other-id"] ?? "");

				if ($my_id_col === "" || $other_id_col === "" || $conn_table === "") {
					throw new BadRequestException(
						"Field `$column` is missing connecting-table settings",
						"invalid_field_settings",
						400
					);
				}

				$conn_schema = BigTree::describeTable($conn_table);

				if (!$conn_schema || empty($conn_schema["columns"][$my_id_col]) || empty($conn_schema["columns"][$other_id_col])) {
					throw new BadRequestException(
						"Connecting-table columns missing for field `$column`",
						"invalid_field_settings",
						400
					);
				}

				$conn_order = $sortable ? "`position` DESC" : "`id` ASC";
				$other_ids = SQL::fetchAllSingle(
					"SELECT `$other_id_col` FROM `$conn_table` WHERE `$my_id_col` = ? ORDER BY $conn_order",
					$entry_id
				) ?: [];

				if (empty($other_ids)) {
					return Response::ok([
						"items" => [],
						"relation" => ["type" => $type, "sortable" => $sortable],
					]);
				}

				$other_ids = array_values(array_map("intval", $other_ids));
				$placeholders = implode(",", array_fill(0, count($other_ids), "?"));
				$rows = SQL::fetchAll(
					"SELECT `id`, `$descriptor` AS `title` FROM `$table` WHERE `id` IN ($placeholders)",
					...$other_ids
				) ?: [];

				// Re-sort to match the stored order from the connecting table —
				// IN() doesn't preserve it.
				$by_id = [];

				foreach ($rows as $row) {
					$by_id[(int)$row["id"]] = (string)($row["title"] ?? "");
				}

				$items = [];

				foreach ($other_ids as $id) {
					if (isset($by_id[$id])) {
						$items[] = ["id" => $id, "title" => $by_id[$id]];
					}
				}

				return Response::ok([
					"items" => $items,
					"relation" => ["type" => $type, "sortable" => $sortable],
				]);
			}

			$where = [];
			$params = [];

			$q = trim((string)($request->query["q"] ?? ""));

			if ($q !== "") {
				$where[] = "`$descriptor` LIKE ?";
				$params[] = "%" . $q . "%";
			}

			$ids_raw = (string)($request->query["ids"] ?? "");

			if ($ids_raw !== "") {
				$ids = [];

				foreach (explode(",", $ids_raw) as $piece) {
					$piece = trim($piece);

					if ($piece !== "" && ctype_digit($piece)) {
						$ids[] = (int)$piece;
					}
				}

				if (empty($ids)) {
					// Asked for specific ids, all invalid — no rows match.
					return Response::ok([
						"items" => [],
						"relation" => ["type" => $type, "sortable" => $sortable],
					]);
				}

				$placeholders = implode(",", array_fill(0, count($ids), "?"));
				$where[] = "`id` IN ($placeholders)";

				foreach ($ids as $id) {
					$params[] = $id;
				}
			}

			$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
			$limit = $ids_raw !== "" ? "" : "LIMIT 250";
			$sql = "SELECT `id`, `$descriptor` AS `title` FROM `$table` $where_sql ORDER BY $order_by $limit";
			$rows = SQL::fetchAll($sql, ...$params);

			$items = array_map(function ($row) {

				return [
					"id" => (int)$row["id"],
					"title" => (string)($row["title"] ?? ""),
				];
			}, $rows ?: []);

			return Response::ok([
				"items" => $items,
				"relation" => [
					"type" => $type,
					"sortable" => $sortable,
				],
			]);
		}

		/**
		 * Build a safe ORDER BY clause from a settings-stored sort string. The
		 * legacy admin allowed any sort expression (e.g. "name ASC"), so we
		 * parse out the first identifier and validate it against the actual
		 * column list before re-attaching the direction.
		 */
		private function safeOrderClause(string $raw, array $columns, string $fallback): string {
			$trimmed = trim($raw);

			if ($trimmed !== "") {
				if (preg_match('/^`?([A-Za-z0-9_-]+)`?(?:\s+(ASC|DESC))?\s*$/i', $trimmed, $m)) {
					$col = $m[1];
					$dir = strtoupper($m[2] ?? "ASC");

					if (!empty($columns[$col])) {
						return "`$col` $dir";
					}
				}
			}

			return "`$fallback` ASC";
		}

		// — Action CRUD —

		public function createAction(Request $request) {
			$module_id = $request->route_params["id"];
			$this->loadModule($module_id);
			$d = $request->body;

			$route_raw = (string)($d["route"] ?? "");

			if ($route_raw !== "" && (!ctype_alnum(str_replace("-", "", $route_raw)) || strlen($route_raw) > 127)) {
				throw new BadRequestException("Action route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route", 400);
			}

			$route = BigTreeAdmin::uniqueModuleActionRoute($module_id, $route_raw);
			$position = (int)($d["position"] ?? 0);
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			if ($position === 0) {
				$context->incrementPosition("actions");
			}

			$id = $context->insert("actions", [
				"route" => $route,
				"in_nav" => !empty($d["in_nav"]) ? "on" : "",
				"class" => (string)($d["icon"] ?? ($d["class"] ?? "")),
				"name" => BigTree::safeEncode((string)$d["name"]),
				"form" => !empty($d["form"]) ? $d["form"] : null,
				"view" => !empty($d["view"]) ? $d["view"] : null,
				"report" => !empty($d["report"]) ? $d["report"] : null,
				"level" => (int)($d["level"] ?? 0),
				"position" => $position,
			]);

			return Response::created($this->getSubResource($module_id, "actions", $id), null);
		}

		public function updateAction(Request $request) {
			$module_id = $request->route_params["id"];
			$action_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);

			$existing = $this->findSub($module["actions"] ?? [], $action_id);

			if (!$existing) {
				throw new NotFoundException("Action $action_id not found", "resource_not_found", 404);
			}

			$d = $request->body;
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			$update = [];

			if (isset($d["name"])) {
				$update["name"] = BigTree::safeEncode((string)$d["name"]);
			}

			if (array_key_exists("in_nav", $d)) {
				$update["in_nav"] = !empty($d["in_nav"]) ? "on" : "";
			}

			if (isset($d["icon"])) {
				$update["class"] = (string)$d["icon"];
			}

			if (isset($d["class"])) {
				$update["class"] = (string)$d["class"];
			}

			if (isset($d["level"])) {
				$update["level"] = (int)$d["level"];
			}

			if (array_key_exists("form", $d)) {
				$update["form"] = $d["form"] ? $d["form"] : null;
			}

			if (array_key_exists("view", $d)) {
				$update["view"] = $d["view"] ? $d["view"] : null;
			}

			if (array_key_exists("report", $d)) {
				$update["report"] = $d["report"] ? $d["report"] : null;
			}

			if (isset($d["position"])) {
				$update["position"] = (int)$d["position"];
			}

			if (isset($d["route"])) {
				$route_raw = (string)$d["route"];

				if ($route_raw !== "" && (!ctype_alnum(str_replace("-", "", $route_raw)) || strlen($route_raw) > 127)) {
					throw new BadRequestException("Action route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route", 400);
				}

				$update["route"] = BigTreeAdmin::uniqueModuleActionRoute($module_id, $route_raw, $action_id);
			}

			if ($update) {
				$context->update("actions", $action_id, $update);
			}
			return Response::ok($this->getSubResource($module_id, "actions", $action_id));
		}

		public function deleteAction(Request $request) {
			$module_id = $request->route_params["id"];
			$action_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["actions"] ?? [], $action_id);

			if (!$existing) {
				throw new NotFoundException("Action $action_id not found", "resource_not_found", 404);
			}

			$context = BigTreeJSONDB::getSubset("modules", $module_id);
			$context->delete("actions", $action_id);

			// Cascade: if this action referenced a form/view/report and no other action does, delete it too.
			$siblings = array_values(array_filter($module["actions"] ?? [], function ($a) use ($action_id) {

				return ($a["id"] ?? "") != $action_id;
			}));

			foreach (["form", "view", "report"] as $rel) {
				$target_id = $existing[$rel] ?? null;

				if (!$target_id) {
					continue;
				}
				$still_referenced = false;

				foreach ($siblings as $sib) {
					if (($sib[$rel] ?? null) == $target_id) { $still_referenced = true; break; }
				}

				if (!$still_referenced) {
					$bucket = $rel . "s"; // form → forms, view → views, report → reports
					$context->delete($bucket, $target_id);
				}
			}

			return Response::noContent();
		}

		public function reorderActions(Request $request) {
			$module_id = $request->route_params["id"];
			$this->loadModule($module_id);
			$ids = array_map("intval", (array)$request->body["ids"]);
			$context = BigTreeJSONDB::getSubset("modules", $module_id);
			$pos = count($ids);

			foreach ($ids as $aid) {
				$context->update("actions", $aid, ["position" => $pos--]);
			}

			return Response::noContent();
		}

		// — Form CRUD —

		public function createForm(Request $request) {
			$module_id = $request->route_params["id"];
			$this->loadModule($module_id);
			$d = $request->body;

			$context = BigTreeJSONDB::getSubset("modules", $module_id);
			$id = $context->insert("forms", [
				"title" => BigTree::safeEncode((string)$d["title"]),
				"table" => (string)($d["table"] ?? ""),
				"fields" => $this->cleanFormFields($d["fields"] ?? []),
				"default_position" => (string)($d["default_position"] ?? ""),
				"return_view" => !empty($d["return_view"]) ? $d["return_view"] : null,
				"return_url" => BigTree::safeEncode((string)($d["return_url"] ?? "")),
				"tagging" => !empty($d["tagging"]) ? "on" : "",
				"open_graph" => !empty($d["open_graph"]) ? "on" : "",
				"hooks" => is_array($d["hooks"] ?? null) ? $d["hooks"] : [],
			]);

			if (!empty($d["table"])) {
				BigTreeAdmin::updateModuleViewColumnNumericStatusForTable($d["table"]);
			}

			return Response::created($this->getSubResource($module_id, "forms", $id), null);
		}

		public function updateForm(Request $request) {
			$module_id = $request->route_params["id"];
			$form_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["forms"] ?? [], $form_id);

			if (!$existing) {
				throw new NotFoundException("Form $form_id not found", "resource_not_found", 404);
			}

			$d = $request->body;
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			$update = [];

			if (isset($d["title"])) {
				$update["title"] = BigTree::safeEncode((string)$d["title"]);
			}

			if (isset($d["table"])) {
				$update["table"] = (string)$d["table"];
			}

			if (isset($d["fields"])) {
				$update["fields"] = $this->cleanFormFields($d["fields"]);
			}

			if (isset($d["default_position"])) {
				$update["default_position"] = (string)$d["default_position"];
			}

			if (array_key_exists("return_view", $d)) {
				$update["return_view"] = $d["return_view"] ? $d["return_view"] : null;
			}

			if (isset($d["return_url"])) {
				$update["return_url"] = BigTree::safeEncode((string)$d["return_url"]);
			}

			if (array_key_exists("tagging", $d)) {
				$update["tagging"] = !empty($d["tagging"]) ? "on" : "";
			}

			if (array_key_exists("open_graph", $d)) {
				$update["open_graph"] = !empty($d["open_graph"]) ? "on" : "";
			}

			if (isset($d["hooks"]) && is_array($d["hooks"])) {
				$update["hooks"] = $d["hooks"];
			}

			if ($update) {
				$context->update("forms", $form_id, $update);
				$new_table = $update["table"] ?? ($existing["table"] ?? "");

				if ($new_table !== "") {
					BigTreeAdmin::updateModuleViewColumnNumericStatusForTable($new_table);
				}
			}

			// If the title changed and this form is referenced by an add/edit action,
			// keep the action label in sync (matches legacy updateModuleForm).
			if (isset($update["title"])) {
				$title = (string)$d["title"];

				foreach ($module["actions"] ?? [] as $action) {
					if (($action["form"] ?? "") != $form_id) {
						continue;
					}

					if (strpos((string)$action["route"], "add") === 0) {
						$context->update("actions", $action["id"], ["name" => "Add $title"]);
					} elseif (strpos((string)$action["route"], "edit") === 0) {
						$context->update("actions", $action["id"], ["name" => "Edit $title"]);
					}
				}
			}

			return Response::ok($this->getSubResource($module_id, "forms", $form_id));
		}

		public function deleteForm(Request $request) {
			$module_id = $request->route_params["id"];
			$form_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["forms"] ?? [], $form_id);

			if (!$existing) {
				throw new NotFoundException("Form $form_id not found", "resource_not_found", 404);
			}

			$context = BigTreeJSONDB::getSubset("modules", $module_id);
			$context->delete("forms", $form_id);

			foreach ($module["actions"] ?? [] as $action) {
				if (($action["form"] ?? "") == $form_id) {
					$context->delete("actions", $action["id"]);
				}
			}

			return Response::noContent();
		}

		// — View CRUD —

		public function createView(Request $request) {
			$module_id = $request->route_params["id"];
			$this->loadModule($module_id);
			$d = $request->body;
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			$id = $context->insert("views", [
				"title" => BigTree::safeEncode((string)$d["title"]),
				"description" => BigTree::safeEncode((string)($d["description"] ?? "")),
				"table" => (string)($d["table"] ?? ""),
				"type" => (string)($d["type"] ?? ""),
				"settings" => is_array($d["settings"] ?? null) ? $d["settings"] : [],
				"fields" => is_array($d["fields"] ?? null) ? $d["fields"] : [],
				"actions" => is_array($d["actions"] ?? null) ? $d["actions"] : [],
				"related_form" => !empty($d["related_form"]) ? $d["related_form"] : null,
				"preview_url" => BigTree::safeEncode((string)($d["preview_url"] ?? "")),
				"exclude_from_search" => !empty($d["exclude_from_search"]),
			]);

			if (!empty($d["table"])) {
				BigTreeAdmin::updateModuleViewColumnNumericStatusForTable($d["table"]);
			}

			return Response::created($this->getSubResource($module_id, "views", $id), null);
		}

		public function updateView(Request $request) {
			$module_id = $request->route_params["id"];
			$view_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["views"] ?? [], $view_id);

			if (!$existing) {
				throw new NotFoundException("View $view_id not found", "resource_not_found", 404);
			}

			$d = $request->body;
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			$update = [];

			if (isset($d["title"])) {
				$update["title"] = BigTree::safeEncode((string)$d["title"]);
			}

			if (isset($d["description"])) {
				$update["description"] = BigTree::safeEncode((string)$d["description"]);
			}

			if (isset($d["table"])) {
				$update["table"] = (string)$d["table"];
			}

			if (isset($d["type"])) {
				$update["type"] = (string)$d["type"];
			}

			if (isset($d["settings"]) && is_array($d["settings"])) {
				$update["settings"] = $d["settings"];
			}

			if (isset($d["fields"]) && is_array($d["fields"])) {
				$update["fields"] = $d["fields"];
			}

			if (isset($d["actions"]) && is_array($d["actions"])) {
				$update["actions"] = $d["actions"];
			}

			if (array_key_exists("related_form", $d)) {
				$update["related_form"] = $d["related_form"] ? $d["related_form"] : null;
			}

			if (isset($d["preview_url"])) {
				$update["preview_url"] = BigTree::safeEncode((string)$d["preview_url"]);
			}

			if (array_key_exists("exclude_from_search", $d)) {
				$update["exclude_from_search"] = !empty($d["exclude_from_search"]);
			}

			if ($update) {
				$context->update("views", $view_id, $update);
				$new_table = $update["table"] ?? ($existing["table"] ?? "");

				if ($new_table !== "") {
					BigTreeAdmin::updateModuleViewColumnNumericStatusForTable($new_table);
				}
			}

			return Response::ok($this->getSubResource($module_id, "views", $view_id));
		}

		public function deleteView(Request $request) {
			$module_id = $request->route_params["id"];
			$view_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["views"] ?? [], $view_id);

			if (!$existing) {
				throw new NotFoundException("View $view_id not found", "resource_not_found", 404);
			}

			$context = BigTreeJSONDB::getSubset("modules", $module_id);
			$context->delete("views", $view_id);

			foreach ($module["actions"] ?? [] as $action) {
				if (($action["view"] ?? "") == $view_id) {
					$context->delete("actions", $action["id"]);
				}
			}

			return Response::noContent();
		}

		// — Report CRUD —

		public function createReport(Request $request) {
			$module_id = $request->route_params["id"];
			$this->loadModule($module_id);
			$d = $request->body;
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			$id = $context->insert("reports", [
				"title" => BigTree::safeEncode((string)$d["title"]),
				"table" => (string)($d["table"] ?? ""),
				"type" => (string)($d["type"] ?? "csv"),
				"filters" => is_array($d["filters"] ?? null) ? $d["filters"] : [],
				"fields" => $d["fields"] ?? "",
				"parser" => (string)($d["parser"] ?? ""),
				"view" => !empty($d["view"]) ? $d["view"] : null,
				"streaming" => !empty($d["streaming"]),
			]);

			return Response::created($this->getSubResource($module_id, "reports", $id), null);
		}

		public function updateReport(Request $request) {
			$module_id = $request->route_params["id"];
			$report_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["reports"] ?? [], $report_id);

			if (!$existing) {
				throw new NotFoundException("Report $report_id not found", "resource_not_found", 404);
			}

			$d = $request->body;
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			$update = [];

			if (isset($d["title"])) {
				$update["title"] = BigTree::safeEncode((string)$d["title"]);
			}

			if (isset($d["table"])) {
				$update["table"] = (string)$d["table"];
			}

			if (isset($d["type"])) {
				$update["type"] = (string)$d["type"];
			}

			if (isset($d["filters"]) && is_array($d["filters"])) {
				$update["filters"] = $d["filters"];
			}

			if (array_key_exists("fields", $d)) {
				$update["fields"] = $d["fields"];
			}

			if (isset($d["parser"])) {
				$update["parser"] = (string)$d["parser"];
			}

			if (array_key_exists("view", $d)) {
				$update["view"] = $d["view"] ? $d["view"] : null;
			}

			if (array_key_exists("streaming", $d)) {
				$update["streaming"] = !empty($d["streaming"]);
			}

			if ($update) {
				$context->update("reports", $report_id, $update);
			}
			return Response::ok($this->getSubResource($module_id, "reports", $report_id));
		}

		public function deleteReport(Request $request) {
			$module_id = $request->route_params["id"];
			$report_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["reports"] ?? [], $report_id);

			if (!$existing) {
				throw new NotFoundException("Report $report_id not found", "resource_not_found", 404);
			}

			$context = BigTreeJSONDB::getSubset("modules", $module_id);
			$context->delete("reports", $report_id);

			foreach ($module["actions"] ?? [] as $action) {
				if (($action["report"] ?? "") == $report_id) {
					$context->delete("actions", $action["id"]);
				}
			}

			return Response::noContent();
		}

		// — Embed-form CRUD —

		public function createEmbedForm(Request $request) {
			$module_id = $request->route_params["id"];
			$this->loadModule($module_id);
			$d = $request->body;
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			$id = $context->insert("embed-forms", [
				"title" => BigTree::safeEncode((string)$d["title"]),
				"table" => (string)($d["table"] ?? ""),
				"fields" => $this->cleanFormFields($d["fields"] ?? []),
				"hooks" => is_array($d["hooks"] ?? null) ? $d["hooks"] : [],
				"default_position" => (string)($d["default_position"] ?? ""),
				"default_pending" => !empty($d["default_pending"]) ? "on" : "",
				"css" => (string)($d["css"] ?? ""),
				"redirect_url" => BigTree::safeEncode((string)($d["redirect_url"] ?? "")),
				"thank_you_message" => (string)($d["thank_you_message"] ?? ""),
				"hash" => bin2hex(random_bytes(16)),
			]);

			return Response::created($this->getSubResource($module_id, "embed-forms", $id), null);
		}

		public function updateEmbedForm(Request $request) {
			$module_id = $request->route_params["id"];
			$ef_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["embed-forms"] ?? [], $ef_id);

			if (!$existing) {
				throw new NotFoundException("Embed form $ef_id not found", "resource_not_found", 404);
			}

			$d = $request->body;
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			$update = [];

			if (isset($d["title"])) {
				$update["title"] = BigTree::safeEncode((string)$d["title"]);
			}

			if (isset($d["table"])) {
				$update["table"] = (string)$d["table"];
			}

			if (isset($d["fields"])) {
				$update["fields"] = $this->cleanFormFields($d["fields"]);
			}

			if (isset($d["hooks"]) && is_array($d["hooks"])) {
				$update["hooks"] = $d["hooks"];
			}

			if (isset($d["default_position"])) {
				$update["default_position"] = (string)$d["default_position"];
			}

			if (array_key_exists("default_pending", $d)) {
				$update["default_pending"] = !empty($d["default_pending"]) ? "on" : "";
			}

			if (isset($d["css"])) {
				$update["css"] = (string)$d["css"];
			}

			if (isset($d["redirect_url"])) {
				$update["redirect_url"] = BigTree::safeEncode((string)$d["redirect_url"]);
			}

			if (isset($d["thank_you_message"])) {
				$update["thank_you_message"] = (string)$d["thank_you_message"];
			}

			if ($update) {
				$context->update("embed-forms", $ef_id, $update);
			}
			return Response::ok($this->getSubResource($module_id, "embed-forms", $ef_id));
		}

		public function deleteEmbedForm(Request $request) {
			$module_id = $request->route_params["id"];
			$ef_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["embed-forms"] ?? [], $ef_id);

			if (!$existing) {
				throw new NotFoundException("Embed form $ef_id not found", "resource_not_found", 404);
			}
			$context = BigTreeJSONDB::getSubset("modules", $module_id);
			$context->delete("embed-forms", $ef_id);

			return Response::noContent();
		}

		// — sub-resource helpers —

		private function loadModule($id) {
			$m = BigTreeJSONDB::get("modules", $id);

			if (!$m) {
				throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			}
			return $m;
		}

		private function getSubResource($module_id, $bucket, $sub_id) {
			$module = BigTreeJSONDB::get("modules", $module_id);
			$found = $this->findSub($module[$bucket] ?? [], $sub_id);

			if (!$found) {
				throw new NotFoundException("Sub-resource $sub_id not found", "resource_not_found", 404);
			}
			return $found;
		}

		private function findSub(array $rows, $id) {
			foreach ($rows as $row) {
				if (($row["id"] ?? "") == $id) {
					return $row;
				}
			}

			return null;
		}

		private function sortByPosition(array $rows) {
			usort($rows, function ($a, $b) {
				$ap = (int)($a["position"] ?? 0);
				$bp = (int)($b["position"] ?? 0);

				if ($ap === $bp) {
					return 0;
				}
				return $ap > $bp ? -1 : 1;
			});

			return array_values($rows);
		}

		/**
		 * Normalize a fields-array submission from the SPA into the legacy storage
		 * shape: each element has column/type/title/subtitle/settings; settings goes
		 * through arrayFilterRecursive to drop empty leaves (matches legacy behavior).
		 *
		 * Accepts both list-shaped [{column, type, ...}, ...] and dict-shaped
		 * { column_name: {type, ...}, ... } inputs for backwards compatibility.
		 */
		private function cleanFormFields($fields) {
			if (!is_array($fields)) {
				return [];
			}
			$out = [];

			foreach ($fields as $key => $data) {
				if (!is_array($data)) {
					continue;
				}
				$column = (string)($data["column"] ?? (is_string($key) ? $key : ""));

				if ($column === "") {
					continue;
				}
				$settings = $data["settings"] ?? ($data["options"] ?? []);

				if (is_string($settings)) {
					$settings = json_decode($settings, true) ?: [];
				}
				$settings = BigTree::arrayFilterRecursive(is_array($settings) ? $settings : []);
				$out[] = [
					"column" => $column,
					"type" => BigTree::safeEncode((string)($data["type"] ?? "text")),
					"title" => BigTree::safeEncode((string)($data["title"] ?? "")),
					"subtitle" => BigTree::safeEncode((string)($data["subtitle"] ?? "")),
					"settings" => $settings,
				];
			}

			return $out;
		}

		// — groups —

		public function listGroups(Request $request) {

			return Response::ok(BigTreeJSONDB::getAll("module-groups", "position", "DESC"));
		}

		public function getGroup(Request $request) {
			$id = $request->route_params["id"];
			$g = BigTreeJSONDB::get("module-groups", $id);

			if (!$g) {
				throw new NotFoundException("Module group $id not found", "resource_not_found", 404);
			}
			return Response::ok($g);
		}

		public function createGroup(Request $request) {
			$d = $request->body;
			$id = BigTreeJSONDB::insert("module-groups", [
				"name" => BigTree::safeEncode($d["name"]),
				"route" => $d["route"] ?? BigTreeCMS::urlify($d["name"]),
				"position" => 0,
			]);

			return Response::created(BigTreeJSONDB::get("module-groups", $id), null);
		}

		public function updateGroup(Request $request) {
			$id = $request->route_params["id"];
			$existing = BigTreeJSONDB::get("module-groups", $id);

			if (!$existing) {
				throw new NotFoundException("Module group $id not found", "resource_not_found", 404);
			}
			$d = $request->body;
			BigTreeJSONDB::update("module-groups", $id, array_merge($existing, array_filter([
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : null,
				"route" => $d["route"] ?? null,
			], function ($v) { return $v !== null; })));

			return Response::ok(BigTreeJSONDB::get("module-groups", $id));
		}

		public function deleteGroup(Request $request) {
			$id = $request->route_params["id"];

			if (!BigTreeJSONDB::exists("module-groups", $id)) {
				throw new NotFoundException("Module group $id not found", "resource_not_found", 404);
			}

			BigTreeJSONDB::delete("module-groups", $id);
			// Detach modules from this group
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $m) {
				if (($m["group"] ?? "") == $id) {
					BigTreeJSONDB::update("modules", $m["id"], array_merge($m, ["group" => null]));
				}
			}

			return Response::noContent();
		}

		// — helpers —

		private function uniqueModuleRoute($base) {
			$route = $base;
			$x = 2;

			while (BigTreeJSONDB::get("modules", $route, "route")) {
				$route = $base . "-" . $x++;
			}

			return $route;
		}

		private function present(array $m, $group_name = "") {

			return [
				"id" => $m["id"],
				"name" => $m["name"] ?? "",
				"group" => $m["group"] ?? null,
				"group_name" => $group_name,
				"class" => $m["class"] ?? "",
				"table" => $m["table"] ?? "",
				"gbp" => $m["gbp"] ?? ["enabled" => false],
				"icon" => $m["icon"] ?? "",
				"route" => $m["route"] ?? "",
				"position" => (int)($m["position"] ?? 0),
				"graphql" => !empty($m["graphql"]),
				"graphql_type" => $m["graphql_type"] ?? "",
			];
		}
	}
