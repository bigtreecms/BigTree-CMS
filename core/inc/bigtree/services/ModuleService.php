<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeAdmin;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use BigTree;
	use SQL;

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

			$out = $this->present($m, $group_name);

			// Caller's access level lets the SPA decide whether to offer
			// "Save & Publish" (publishers only) on this module's forms.
			$out["access"] = PermissionService::userModuleLevel($request->user, $id);

			return Response::ok($out);
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

		// POST /modules/scaffold — the "Module Designer builds it for you" path: from a
		// list of fields, auto-create the table + columns, the module, an add/edit form,
		// and a landing view (with list action). Ports the legacy designer flow
		// (modules/designer/{create,form-create,view-create}.php) into one transaction.
		//
		// All validation runs BEFORE any DDL because CREATE/ALTER TABLE implicitly commit
		// and can't be rolled back — we don't want to half-build on a bad request.
		public function scaffold(Request $request) {
			$d = $request->body;

			$name = trim((string)($d["name"] ?? ""));
			$table = trim((string)($d["table"] ?? ""));
			$class = trim((string)($d["class"] ?? ""));
			$fields_in = is_array($d["fields"] ?? null) ? $d["fields"] : [];

			if ($name === "") {
				throw new BadRequestException("Module name is required", "invalid_name", 400);
			}

			// The table name flows into raw DDL, so it must be a bare identifier.
			if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
				throw new BadRequestException("Table name must contain only letters, numbers, and underscores", "invalid_table", 400);
			}

			if (strlen($table) > 64) {
				throw new BadRequestException("Table name must be 64 characters or fewer", "invalid_table", 400);
			}

			if (BigTree::tableExists($table)) {
				throw new ConflictException("A table named \"$table\" already exists", "table_exists", 409);
			}

			if ($class !== "" && class_exists($class)) {
				throw new ConflictException("A class named \"$class\" already exists", "class_exists", 409);
			}

			// Resolve fields → form-field defs + column DDL, skipping untitled rows and
			// de-duplicating generated column names (mirrors form-create.php).
			$reserved = ["id", "position"];
			$form_fields = [];
			$column_adds = [];
			$used_columns = [];

			foreach ($fields_in as $f) {
				if (!is_array($f)) {
					continue;
				}

				$title = trim((string)($f["title"] ?? ""));

				if ($title === "") {
					continue;
				}

				$base = $this->safeColumnName($title);

				if ($base === "") {
					continue;
				}

				$column = $base;
				$x = 2;

				while (in_array($column, $used_columns, true) || in_array($column, $reserved, true)) {
					$column = $base . $x++;
				}

				$used_columns[] = $column;
				$type = (string)($f["type"] ?? "text");

				$form_fields[] = [
					"column" => $column,
					"type" => $type,
					"title" => $title,
					"subtitle" => (string)($f["subtitle"] ?? ""),
					"settings" => is_array($f["settings"] ?? null) ? $f["settings"] : [],
				];

				$column_adds[] = "ADD COLUMN `$column` " . $this->columnSqlType($type);
			}

			if (count($form_fields) === 0) {
				throw new BadRequestException("Add at least one field with a title", "no_fields", 400);
			}

			$route = $d["route"] ?? BigTreeCMS::urlify($name);

			if (!ctype_alnum(str_replace("-", "", (string)$route)) || strlen((string)$route) > 127) {
				throw new BadRequestException("Module route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route", 400);
			}

			$route = $this->uniqueModuleRoute($route);
			$actions_in = is_array($d["actions"] ?? null) ? $d["actions"] : [];
			$view_type = ($d["view_type"] ?? "searchable") === "draggable" ? "draggable" : "searchable";

			// — Everything validated; create the module record —
			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => BigTree::safeEncode($name),
				"group" => $d["group"] ?? null,
				"class" => $class,
				"table" => $table,
				"gbp" => $d["gbp"] ?? ["enabled" => false],
				"icon" => $d["icon"] ?? "",
				"route" => $route,
				"graphql" => !empty($d["graphql"]) ? "on" : "",
				"graphql_type" => $d["graphql_type"] ?? "",
				"position" => 0,
			]);

			// — Build the table —
			SQL::query("CREATE TABLE `$table` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
			SQL::query("ALTER TABLE `$table` " . implode(", ", $column_adds));

			// Builtin status columns for the chosen actions / view type.
			if (!empty($actions_in["approve"])) {
				SQL::query("ALTER TABLE `$table` ADD COLUMN `approved` CHAR(2) NOT NULL, ADD INDEX `approved` (`approved`)");
			}

			if (!empty($actions_in["feature"])) {
				SQL::query("ALTER TABLE `$table` ADD COLUMN `featured` CHAR(2) NOT NULL, ADD INDEX `featured` (`featured`)");
			}

			if (!empty($actions_in["archive"])) {
				SQL::query("ALTER TABLE `$table` ADD COLUMN `archived` CHAR(2) NOT NULL, ADD INDEX `archived` (`archived`)");
			}

			if ($view_type === "draggable") {
				SQL::query("ALTER TABLE `$table` ADD COLUMN `position` INT(11) NOT NULL, ADD INDEX `position` (`position`)");
			}

			// Singular drives the form ("Add Article"), plural the view ("Viewing Articles").
			$item_title = trim((string)($d["item_title"] ?? "")) ?: $this->singularize($name);
			$view_title = trim((string)($d["view_title"] ?? "")) ?: $this->pluralize($name);

			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			// — Form + add/edit actions —
			$form_id = $context->insert("forms", [
				"title" => BigTree::safeEncode($item_title),
				"table" => $table,
				"fields" => $this->cleanFormFields($form_fields),
				"default_position" => "",
				"return_view" => null,
				"return_url" => "",
				"tagging" => "",
				"open_graph" => "",
				"hooks" => [],
			]);

			$this->insertScaffoldAction($context, $module_id, "Add $item_title", "add", true, "add", $form_id, null, 0);
			$this->insertScaffoldAction($context, $module_id, "Edit $item_title", "edit", false, "edit", $form_id, null, 0);

			// — Landing view (columns mirror the form fields) + list action —
			$view_fields = [];

			foreach ($form_fields as $f) {
				$view_fields[$f["column"]] = ["title" => $f["title"], "parser" => "", "numeric" => ""];
			}

			$view_actions = ["edit" => "on", "delete" => "on"];

			if (!empty($actions_in["approve"])) {
				$view_actions["approve"] = "on";
			}

			if (!empty($actions_in["feature"])) {
				$view_actions["feature"] = "on";
			}

			if (!empty($actions_in["archive"])) {
				$view_actions["archive"] = "on";
			}

			$view_id = $context->insert("views", [
				"title" => BigTree::safeEncode($view_title),
				"description" => "",
				"table" => $table,
				"type" => $view_type,
				"settings" => [],
				"fields" => $view_fields,
				"actions" => $view_actions,
				"related_form" => $form_id,
				"preview_url" => "",
				"exclude_from_search" => false,
			]);

			$this->insertScaffoldAction($context, $module_id, "View $view_title", "", true, "list", null, $view_id, 1);

			BigTreeAdmin::updateModuleViewColumnNumericStatusForTable($table);

			return Response::created($this->present(BigTreeJSONDB::get("modules", $module_id)), null);
		}

		// Insert a module action via the JSONDB subset, mirroring createAction's
		// position handling. Used only by scaffold().
		private function insertScaffoldAction($context, $module_id, $name, $route, $in_nav, $icon, $form, $view, $position) {
			$route = BigTreeAdmin::uniqueModuleActionRoute($module_id, (string)$route);

			if ((int)$position === 0) {
				$context->incrementPosition("actions");
			}

			return $context->insert("actions", [
				"route" => $route,
				"in_nav" => $in_nav ? "on" : "",
				"class" => (string)$icon,
				"name" => BigTree::safeEncode((string)$name),
				"form" => $form ?: null,
				"view" => $view ?: null,
				"report" => null,
				"level" => 0,
				"position" => (int)$position,
			]);
		}

		// Field title → safe MySQL column name (urlify, hyphens→underscores, strip the
		// rest). Mirrors form-create.php's `$cms->urlify` + str_replace.
		private function safeColumnName($title) {
			$name = BigTreeCMS::urlify((string)$title);
			$name = str_replace(["`", "-"], ["", "_"], $name);

			return preg_replace('/[^A-Za-z0-9_]/', "", $name);
		}

		// Field type → column SQL type (the exact legacy form-create.php mapping).
		private function columnSqlType($type) {
			if (in_array($type, ["textarea", "html", "video"], true)) {
				return "TEXT";
			}

			if (in_array($type, ["media-gallery", "matrix", "callouts"], true)) {
				return "LONGTEXT";
			}

			if ($type === "date") {
				return "DATE";
			}

			if ($type === "time") {
				return "TIME";
			}

			if ($type === "datetime") {
				return "DATETIME";
			}

			return "VARCHAR(1024)";
		}

		// "Articles" → "Article" for the form item title (legacy designer/form.php).
		private function singularize($name) {
			if (substr($name, -3) === "ies") {
				return substr($name, 0, -3) . "y";
			}

			return rtrim($name, "s");
		}

		// "Buddy" → "Buddies", "Article" → "Articles" for the view title (designer/view.php).
		private function pluralize($name) {
			$plural = substr($name, -1) !== "s" ? $name . "s" : $name;

			if (substr($plural, -2) === "ys") {
				$plural = substr($plural, 0, -2) . "ies";
			}

			return $plural;
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
			$ids = array_map("strval", (array)$request->body["ids"]);
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

		/**
		 * GET /modules/{id}/actions/{sid}/schema
		 * Render contract for a single action so the SPA knows how to draw + run it.
		 * Mirrors FieldTypeService::schema: a custom "module" action returns its
		 * drawing source (local, run in-context) or asset_url + integrity + trust
		 * (extension-delivered) plus the declared server handler. Auto actions
		 * (form/view/report) and legacy custom-PHP actions return their kind only.
		 */
		public function actionSchema(Request $request) {
			$module = $this->loadModule($request->route_params["id"]);
			$action_id = (string)$request->route_params["sid"];
			$action = $this->findSub($module["actions"] ?? [], $action_id);

			if (!$action) {
				throw new NotFoundException("Action $action_id not found", "resource_not_found", 404);
			}

			$render = $this->actionRenderKind($action);
			$payload = [
				"id" => $action_id,
				"name" => (string)($action["name"] ?? ""),
				"route" => (string)($action["route"] ?? ""),
				"render" => $render,
				"handler" => (string)($action["handler"] ?? ""),
				"contract_version" => (int)($action["contract_version"] ?? 1),
			];

			if ($render === "module") {
				if (!empty($action["asset_url"])) {
					// Installed-extension module: external bundle, SRI-pinned at build.
					$payload["asset_url"] = (string)$action["asset_url"];
					$payload["integrity"] = (string)($action["integrity"] ?? "");
					$payload["trust"] = (string)($action["trust"] ?? "marketplace");
				} else {
					// Locally authored, implicitly trusted — source on disk, in-context.
					// (Fall back to a record-stored source for any pre-migration record.)
					$source = $this->readActionSource($module, (string)($action["route"] ?? ""));

					if ($source === "" && !empty($action["module_source"])) {
						$source = (string)$action["module_source"];
					}

					$payload["module_source"] = $source;
					$payload["trust"] = "local";
				}
			}

			$r = Response::ok($payload);
			$r->header("Cache-Control", "private, max-age=60");

			return $r;
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
		 * Categories for a group-based-permissions module. These are rows from
		 * the module's configured `gbp.other_table`, used by the user editor to
		 * render per-category permission radios. Mirrors the legacy
		 * users/edit.php category lookup: select id + title_field, order by the
		 * title field, and run each label through the optional item_parser.
		 *
		 *   GET /modules/{id}/gbp-categories
		 *
		 * Returns [{ id, title }]. An empty list when the module isn't GBP, the
		 * config is incomplete, or the other table doesn't exist.
		 */
		public function gbpCategories(Request $request) {
			$module = $this->loadModule($request->route_params["id"]);
			$gbp = is_array($module["gbp"] ?? null) ? $module["gbp"] : [];

			if (empty($gbp["enabled"])) {
				return Response::ok([]);
			}

			$other_table = (string)($gbp["other_table"] ?? "");
			$title_field = (string)($gbp["title_field"] ?? "");

			if ($other_table === "" || $title_field === "" || !BigTree::tableExists($other_table)) {
				return Response::ok([]);
			}

			$ot = sqlescape($other_table);
			$tf = sqlescape($title_field);
			$item_parser = $gbp["item_parser"] ?? "";
			$categories = [];
			$q = sqlquery("SELECT id, `$tf` FROM `$ot` ORDER BY `$tf` ASC");

			while ($c = sqlfetch($q)) {
				$title = $c[$title_field] ?? "";

				if (!empty($item_parser) && is_callable($item_parser)) {
					$title = call_user_func($item_parser, $title, $c["id"]);
				}

				$categories[] = [
					"id" => (string)$c["id"],
					"title" => (string)$title,
				];
			}

			return Response::ok($categories);
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
		/**
		 * Resolve the options for a dynamic "list" field (the SPA SelectField).
		 * Mirrors the legacy list field-type draw.php for the non-static list
		 * types:
		 *
		 *   - db      → SELECT id + descriptor from the configured table
		 *   - state   → BigTree::$StateList
		 *   - country → BigTree::$CountryList
		 *   - static  → the stored `list` (also handled client-side, returned here
		 *               for completeness)
		 *
		 * Table/column names come from the developer-defined field settings and are
		 * validated against the live schema before being used in SQL — never raw
		 * client input. The legacy `parser` callback (arbitrary PHP) is intentionally
		 * not run.
		 */
		public function listOptions(Request $request) {
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

			if ((string)($field["type"] ?? "") !== "list") {
				throw new BadRequestException("Field `$column` is not a list field", "invalid_field_type", 400);
			}

			$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
			$list_type = (string)($settings["list_type"] ?? "static");
			$options = [];

			if ($list_type === "db") {
				$options = $this->resolveDatabaseList($settings, $column);
			} elseif ($list_type === "state") {
				foreach (BigTree::$StateList as $abbr => $name) {
					$options[] = ["value" => (string)$abbr, "label" => (string)$name];
				}
			} elseif ($list_type === "country") {
				foreach (BigTree::$CountryList as $country) {
					$options[] = ["value" => (string)$country, "label" => (string)$country];
				}
			} else {
				$list = is_array($settings["list"] ?? null) ? $settings["list"] : [];

				foreach ($list as $item) {
					if (!is_array($item)) {
						continue;
					}

					$value = (string)($item["key"] ?? $item["value"] ?? "");
					$options[] = ["value" => $value, "label" => (string)($item["description"] ?? $item["label"] ?? $value)];
				}
			}

			return Response::ok(["options" => $options]);
		}

		/** Run the validated SELECT behind a db-populated list. */
		private function resolveDatabaseList(array $settings, string $column): array {
			$table = (string)($settings["pop-table"] ?? "");
			$descriptor = (string)($settings["pop-description"] ?? "");
			$sort = (string)($settings["pop-sort"] ?? "");

			if ($table === "" || $descriptor === "") {
				throw new BadRequestException("Field `$column` is missing table/descriptor settings", "invalid_field_settings", 400);
			}

			$schema = BigTree::describeTable($table);

			if (!$schema || empty($schema["columns"])) {
				throw new NotFoundException("Table `$table` not found", "resource_not_found", 404);
			}

			if (empty($schema["columns"][$descriptor]) || empty($schema["columns"]["id"])) {
				throw new BadRequestException("Field `$column` references columns that don't exist on `$table`", "invalid_field_settings", 400);
			}

			$order_by = $this->safeOrderClause($sort, $schema["columns"], $descriptor);
			$options = [];
			$query = \sqlquery("SELECT `id`, `$descriptor` AS `__label` FROM `$table` ORDER BY $order_by");

			while ($row = \sqlfetch($query)) {
				$options[] = ["value" => (string)$row["id"], "label" => (string)$row["__label"]];
			}

			return $options;
		}

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
			$module = $this->loadModule($module_id);
			$d = $request->body;

			$route_raw = (string)($d["route"] ?? "");

			if ($route_raw !== "" && (!ctype_alnum(str_replace("-", "", $route_raw)) || strlen($route_raw) > 127)) {
				throw new BadRequestException("Action route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route", 400);
			}

			// Auto-generate the route from the name when blank (as the UI promises).
			if ($route_raw === "") {
				$route_raw = BigTreeCMS::urlify((string)($d["name"] ?? ""));
			}

			$route = BigTreeAdmin::uniqueModuleActionRoute($module_id, $route_raw);
			$position = (int)($d["position"] ?? 0);
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			if ($position === 0) {
				$context->incrementPosition("actions");
			}

			$record = [
				"route" => $route,
				"in_nav" => !empty($d["in_nav"]) ? "on" : "",
				"class" => (string)($d["icon"] ?? ($d["class"] ?? "")),
				"name" => BigTree::safeEncode((string)$d["name"]),
				"form" => !empty($d["form"]) ? $d["form"] : null,
				"view" => !empty($d["view"]) ? $d["view"] : null,
				"report" => !empty($d["report"]) ? $d["report"] : null,
				"level" => (int)($d["level"] ?? 0),
				"position" => $position,
			];

			// A custom (module) action draws its own UI from a local .mjs and submits
			// to a declared handler. Stamp the marker fields on the record; the source
			// itself lives on disk (written below, once we know the final route).
			$source = null;

			if ((string)($d["render"] ?? "") === "module") {
				$source = $this->validateModuleSource($d);
				$record["render"] = "module";
				$record["trust"] = "local";
				$record["handler"] = $this->validateActionHandler($d["handler"] ?? "");
				$record["contract_version"] = (int)($d["contract_version"] ?? 1);
			}

			$id = $context->insert("actions", $record);

			if ($source !== null) {
				$this->writeActionSource($module, $route, $source);
			}

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

				// Auto-generate from the name when explicitly cleared.
				if ($route_raw === "") {
					$route_raw = BigTreeCMS::urlify((string)($d["name"] ?? ($existing["name"] ?? "")));
				}

				$update["route"] = BigTreeAdmin::uniqueModuleActionRoute($module_id, $route_raw, $action_id);
			}

			// Render mode (custom module action vs auto/legacy). Switching into "module"
			// stamps the markers + writes the source; switching away clears them and
			// removes the source file. Source writes happen after the DB update once the
			// final route is known (the source path is keyed by module + action route).
			$write_source = null;
			$clear_module = false;

			if (array_key_exists("render", $d)) {
				if ((string)$d["render"] === "module") {
					$update["render"] = "module";
					$update["trust"] = "local";
					$update["contract_version"] = (int)($d["contract_version"]
						?? ($existing["contract_version"] ?? 1));

					if (array_key_exists("handler", $d)) {
						$update["handler"] = $this->validateActionHandler($d["handler"]);
					}

					if (array_key_exists("module_source", $d)) {
						$write_source = $this->validateModuleSource($d);
					}
				} else {
					$update["render"] = "";
					$update["trust"] = "";
					$update["handler"] = "";
					$clear_module = true;
				}
			} elseif (array_key_exists("module_source", $d)
				&& $this->actionRenderKind($existing) === "module") {
				// Editing the source of an already-module action without re-sending render.
				$write_source = $this->validateModuleSource($d);
			}

			if ($update) {
				$context->update("actions", $action_id, $update);
			}

			// Filesystem: the source lives alongside where legacy custom actions did —
			// extensions/{ext}/modules/{local}/{action}.js or custom/admin/modules/{route}/{action}.js.
			$old_route = (string)($existing["route"] ?? "");
			$new_route = (string)($update["route"] ?? $old_route);

			if ($new_route !== $old_route && $old_route !== "") {
				$this->moveActionSource($module, $old_route, $new_route);
			}

			if ($write_source !== null) {
				$this->writeActionSource($module, $new_route, $write_source);
			}

			if ($clear_module) {
				$this->deleteActionSource($module, $new_route);
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

			// Remove the on-disk drawing source for a custom (module) action.
			if ($this->actionRenderKind($existing) === "module") {
				$this->deleteActionSource($module, (string)($existing["route"] ?? ""));
			}

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
			$ids = array_map("strval", (array)$request->body["ids"]);
			$context = BigTreeJSONDB::getSubset("modules", $module_id);
			$pos = count($ids);

			foreach ($ids as $aid) {
				$context->update("actions", $aid, ["position" => $pos--]);
			}

			return Response::noContent();
		}

		/**
		 * POST /modules/{id}/actions/{sid}/invoke
		 * Run a custom (module) action's declared server handler with the JSON
		 * payload the SPA submits. The headline security property: the handler must
		 * be explicitly opted in by the module class (getActionHandlers) — a client
		 * can never name an arbitrary method on the class. The action's level and any
		 * per-handler minimum are enforced against the caller. (Auth + CSRF are
		 * handled by the API layer like every other authenticated route.)
		 */
		public function invokeAction(Request $request) {
			$module_id = $request->route_params["id"];
			$action_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$action = $this->findSub($module["actions"] ?? [], $action_id);

			if (!$action) {
				throw new NotFoundException("Action $action_id not found", "resource_not_found", 404);
			}

			$handler = $this->validateActionHandler($action["handler"] ?? "");

			if ($handler === "") {
				throw new BadRequestException("This action has no server handler to run.", "no_handler", 400);
			}

			$class = (string)($module["class"] ?? "");

			if ($class === "" || !class_exists($class)) {
				throw new BadRequestException("This module has no class to run the action on.", "missing_module_class", 400);
			}

			$instance = new $class();

			// Whitelist: the class must explicitly opt the handler in. Never dispatch
			// a client-named method that wasn't declared by the module author.
			$allowed = $this->declaredActionHandlers($instance);

			if (!array_key_exists($handler, $allowed)) {
				throw new AuthorizationException("This action's handler is not enabled on the module.", "forbidden_handler", 403);
			}

			// Level gate: the action's own level plus any per-handler minimum.
			$required = max((int)($action["level"] ?? 0), (int)$allowed[$handler]);
			$user_level = (int)($request->user->level ?? 0);

			if ($user_level < $required) {
				throw new AuthorizationException("You don't have access to run this action.", "permission_denied", 403);
			}

			if (!method_exists($instance, $handler)) {
				throw new BadRequestException("The action handler \"$handler\" is declared but missing on the module class.", "handler_missing", 400);
			}

			$body = is_array($request->body) ? $request->body : [];
			$payload = $body["payload"] ?? null;
			$context = [
				"module_id" => (string)$module_id,
				"action_id" => (string)$action_id,
				"route" => (string)($action["route"] ?? ""),
				"user" => $request->user,
				"selection" => is_array($body["selection"] ?? null) ? $body["selection"] : [],
			];

			$result = $instance->$handler($payload, $context);

			return Response::ok($result);
		}

		/**
		 * The handler methods a module class has opted in, as a name => min-level
		 * map. Two equivalent conventions (the existence of the handler method is
		 * never enough on its own — opt-in is explicit so the client can't reach an
		 * arbitrary method):
		 *
		 *   public static $ActionHandlers = ["doThing" => 1, "other"];   // property
		 *   public function getActionHandlers() { return ["doThing" => 1]; } // method
		 *
		 * Either may be a list of names (min level 0) or a name => level map. No
		 * declaration = nothing runnable.
		 */
		private function declaredActionHandlers($instance) {
			$raw = null;

			if (method_exists($instance, "getActionHandlers")) {
				$raw = $instance->getActionHandlers();
			} else {
				$class = get_class($instance);

				if (property_exists($class, "ActionHandlers")) {
					try {
						$property = new \ReflectionProperty($class, "ActionHandlers");

						if ($property->isStatic() && $property->isPublic()) {
							$raw = $property->getValue();
						}
					} catch (\Throwable $e) {
						$raw = null;
					}
				}
			}

			if (!is_array($raw)) {
				return [];
			}

			$map = [];

			foreach ($raw as $key => $value) {
				if (is_int($key)) {
					$map[(string)$value] = 0;
				} else {
					$map[(string)$key] = (int)$value;
				}
			}

			return $map;
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

		/**
		 * Surface everything the SPA needs to draw the report filter form before
		 * running it: the report itself, its related view and form (for sort
		 * column options), and the resolved dropdown options for any
		 * `dropdown`-typed filter.
		 *
		 *   GET /modules/{id}/reports/{sid}/prepare
		 *
		 * Dropdown options come from the related form's poplist when the filter
		 * column is a db-backed list field, otherwise from a DISTINCT() on the
		 * report's underlying table. This mirrors the legacy dropdown.php partial
		 * so the SPA renders the same option set the PHP admin showed.
		 */
		public function prepareReport(Request $request) {
			$module_id = $request->route_params["id"];
			$report_id = $request->route_params["sid"];

			$this->loadModule($module_id);
			$report = \BigTreeAutoModule::getReport($report_id);

			if (!$report) {
				throw new NotFoundException("Report $report_id not found", "resource_not_found", 404);
			}

			$form = \BigTreeAutoModule::getRelatedFormForReport($report);
			$view = !empty($report["view"])
				? \BigTreeAutoModule::getView($report["view"])
				: \BigTreeAutoModule::getRelatedViewForReport($report);

			$filter_options = [];

			if (!empty($report["filters"]) && is_array($report["filters"])) {
				foreach ($report["filters"] as $column => $filter) {
					if (($filter["type"] ?? "") !== "dropdown") {
						continue;
					}

					$filter_options[$column] = $this->resolveDropdownFilterOptions(
						$column,
						$report,
						$form
					);
				}
			}

			return Response::ok([
				"report" => $report,
				"view" => $view,
				"form" => $form,
				"filter_options" => $filter_options,
			]);
		}

		/**
		 * Execute a saved report and return its rows.
		 *
		 *   POST /modules/{id}/reports/{sid}/run
		 *   {
		 *     "filters": { <filter-id>: <value | { start, end }> },
		 *     "sort":    { "field": "<column>", "order": "ASC" | "DESC" }
		 *   }
		 *
		 * Mirrors `BigTreeAutoModule::getReportResults` from the legacy admin: the
		 * report's stored type/parser/poplist handling is applied server-side and
		 * the SPA only ever sees parsed rows, the related view/form config, and a
		 * row count.
		 *
		 * Returns:
		 *   {
		 *     report: { id, title, type, fields, parser, streaming, view, table, module },
		 *     view:   <view config | null>,
		 *     form:   <form config | null>,
		 *     items:  [ <row>, ... ],
		 *     meta:   { count }
		 *   }
		 */
		public function runReport(Request $request) {
			$module_id = $request->route_params["id"];
			$report_id = $request->route_params["sid"];

			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["reports"] ?? [], $report_id);

			if (!$existing) {
				throw new NotFoundException("Report $report_id not found", "resource_not_found", 404);
			}

			$report = \BigTreeAutoModule::getReport($report_id);

			if (!$report) {
				throw new NotFoundException("Report $report_id not found", "resource_not_found", 404);
			}

			$form = \BigTreeAutoModule::getRelatedFormForReport($report);
			$view = !empty($report["view"])
				? \BigTreeAutoModule::getView($report["view"])
				: \BigTreeAutoModule::getRelatedViewForReport($report);

			$body = $request->body ?? [];
			$filters = is_array($body["filters"] ?? null) ? $body["filters"] : [];
			$sort_field = (string)($body["sort"]["field"] ?? "id");
			$sort_order = (string)($body["sort"]["order"] ?? "DESC");

			$items = \BigTreeAutoModule::getReportResults(
				$report,
				$view,
				$form,
				$filters,
				$sort_field,
				$sort_order
			);

			$items = is_array($items) ? array_values($items) : [];

			return Response::ok([
				"report" => $report,
				"view" => $view,
				"form" => $form,
				"items" => $items,
				"meta" => ["count" => count($items)],
			]);
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

		/**
		 * Public lookup for the SPA embed form route.
		 *
		 *   GET /embed-forms/{hash}
		 *
		 * Returns just the config the renderer needs: the form's fields, table,
		 * optional CSS URL, thank-you message and redirect URL. Hooks are
		 * intentionally NOT returned — they are PHP callbacks that run
		 * server-side during submission.
		 */
		public function publicGetEmbedForm(Request $request) {
			$hash = (string)($request->route_params["hash"] ?? "");

			if ($hash === "") {
				throw new NotFoundException("Embed form not found", "resource_not_found", 404);
			}

			$form = \BigTreeAutoModule::getEmbedFormByHash($hash);

			if (!$form) {
				throw new NotFoundException("Embed form not found", "resource_not_found", 404);
			}

			return Response::ok([
				"id" => $form["id"] ?? "",
				"module" => $form["module"] ?? "",
				"title" => $form["title"] ?? "",
				"table" => $form["table"] ?? "",
				"fields" => array_values((array)($form["fields"] ?? [])),
				"css" => $form["css"] ?? "",
				"thank_you_message" => $form["thank_you_message"] ?? "",
				"redirect_url" => $form["redirect_url"] ?? "",
				"default_pending" => !empty($form["default_pending"]),
			]);
		}

		/**
		 * Public submit handler for a SPA embed form.
		 *
		 *   POST /embed-forms/{hash}/submit
		 *   { "values": { <column>: <value>, ... } }
		 *
		 * Mirrors the legacy embeddable-form/process.php: runs the form's pre
		 * hook (if any), processes each field via BigTreeAdmin::processField to
		 * apply per-type sanitization, then writes either directly via
		 * createItem or as a pending change via createPendingItem (driven by
		 * `default_pending` on the form). Post/publish hooks run on success.
		 *
		 * File uploads, image crops, and hashcash spam protection are TODO —
		 * the SPA submits JSON, not multipart, so we defer those until the
		 * embed-form upload story is wired up.
		 *
		 * Returns:
		 *   { id, status: "published" | "pending", thank_you_message, redirect_url }
		 */
		public function publicSubmitEmbedForm(Request $request) {
			$hash = (string)($request->route_params["hash"] ?? "");

			if ($hash === "") {
				throw new NotFoundException("Embed form not found", "resource_not_found", 404);
			}

			$form = \BigTreeAutoModule::getEmbedFormByHash($hash);

			if (!$form) {
				throw new NotFoundException("Embed form not found", "resource_not_found", 404);
			}

			$body = $request->body ?? [];
			$post_data = is_array($body["values"] ?? null) ? $body["values"] : [];

			if (!empty($form["hooks"]["pre"]) && is_callable($form["hooks"]["pre"])) {
				$pre = call_user_func($form["hooks"]["pre"], $post_data);

				if (is_array($pre)) {
					$post_data = array_merge($post_data, $pre);
				}
			}

			$entry = [];
			$fields = (array)($form["fields"] ?? []);

			// Field processors (image/file/video-reference) push referenced resource
			// ids into IRLsCreated as they run; reset it so we allocate only this
			// submission's resources after the row is written (mirrors the legacy
			// embeddable-form/process.php).
			\BigTreeAdmin::$IRLsCreated = [];

			foreach ($fields as $resource) {
				if (!is_array($resource)) {
					continue;
				}

				$column = (string)($resource["column"] ?? "");

				if ($column === "") {
					continue;
				}

				$field = [
					"type" => $resource["type"] ?? "text",
					"title" => $resource["title"] ?? "",
					"key" => $column,
					"settings" => $resource["settings"] ?? $resource["options"] ?? [],
					"ignore" => false,
					"input" => $post_data[$column] ?? null,
					"file_input" => null,
				];

				$output = \BigTreeAdmin::processField($field);

				if (!is_null($output)) {
					$entry[$column] = $output;
				}
			}

			$entry = \BigTreeAutoModule::sanitizeData($form["table"], $entry);

			$tags = is_array($post_data["_tags"] ?? null) ? $post_data["_tags"] : [];
			$pending = !empty($form["default_pending"]);

			if ($pending) {
				$publish_hook = $form["hooks"]["publish"] ?? "";
				$edit_id = \BigTreeAutoModule::createPendingItem(
					$form["module"] ?? "",
					$form["table"],
					$entry,
					[],
					$tags,
					$publish_hook,
					true
				);
				$status = "pending";
			} else {
				$edit_id = \BigTreeAutoModule::createItem(
					$form["table"],
					$entry,
					[],
					$tags
				);
				$status = "published";
			}

			// Track resource allocation against the new row (pending rows key on "p{id}").
			\BigTreeAdmin::allocateResources($form["table"], $pending ? "p".$edit_id : $edit_id);

			if (!empty($form["hooks"]["post"]) && is_callable($form["hooks"]["post"])) {
				call_user_func($form["hooks"]["post"], $edit_id, $entry, !$pending);
			}

			if (!$pending && !empty($form["hooks"]["publish"]) && is_callable($form["hooks"]["publish"])) {
				call_user_func($form["hooks"]["publish"], $form["table"], $edit_id, $entry, [], $tags);
			}

			return Response::ok([
				"id" => $edit_id,
				"status" => $status,
				"thank_you_message" => $form["thank_you_message"] ?? "",
				"redirect_url" => $form["redirect_url"] ?? "",
			]);
		}

		/**
		 * Resolve the option set for a dropdown report filter. Honors the
		 * related form's db-populated list config when present, otherwise falls
		 * back to a DISTINCT() on the report's underlying table — matching the
		 * legacy admin's dropdown.php partial.
		 *
		 * Returns a list of `{ value, label }` so the SPA can render `<option>`
		 * elements without re-querying anything.
		 */
		private function resolveDropdownFilterOptions(string $column, array $report, $form): array {
			$field = null;

			if (is_array($form) && is_array($form["fields"] ?? null)) {
				foreach ($form["fields"] as $candidate) {
					if (($candidate["column"] ?? "") === $column) {
						$field = $candidate;

						break;
					}
				}
			}

			$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
			$out = [];

			if ($field && ($field["type"] ?? "") === "list" && ($settings["list_type"] ?? "") === "db") {
				$pop_table = (string)($settings["pop-table"] ?? "");
				$pop_description = (string)($settings["pop-description"] ?? "");
				$pop_sort = (string)($settings["pop-sort"] ?? "");

				if ($pop_table === "" || $pop_description === "") {
					return $out;
				}

				$pop_table_safe = str_replace("`", "", $pop_table);
				$pop_description_safe = str_replace("`", "", $pop_description);
				$order_by = $pop_sort !== "" ? str_replace(";", "", $pop_sort) : "id ASC";
				$query = "SELECT id, `$pop_description_safe` FROM `$pop_table_safe` ORDER BY $order_by";
				$rs = sqlquery($query);

				while ($row = sqlfetch($rs)) {
					$out[] = [
						"value" => $row["id"],
						"label" => (string)$row[$pop_description],
					];
				}

				return $out;
			}

			$table = (string)($report["table"] ?? "");

			if ($table === "") {
				return $out;
			}

			$col_safe = str_replace("`", "", $column);
			$table_safe = str_replace("`", "", $table);
			$rs = sqlquery("SELECT DISTINCT(`$col_safe`) AS v FROM `$table_safe` ORDER BY `$col_safe`");

			while ($row = sqlfetch($rs)) {
				$v = (string)$row["v"];
				$out[] = ["value" => $v, "label" => $v];
			}

			return $out;
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

		// — Custom (module) action helpers —

		/**
		 * Derive an action's render contract for the SPA. An explicit "render" key
		 * wins; an asset_url / module_source means a custom JS module; a
		 * form/view/report means an auto action; anything else is a legacy custom-PHP
		 * action the SPA can't run natively.
		 */
		private function actionRenderKind(array $action) {
			if (!empty($action["render"])) {
				return (string)$action["render"];
			}

			if (!empty($action["asset_url"]) || !empty($action["module_source"])) {
				return "module";
			}

			if (!empty($action["form"]) || !empty($action["view"]) || !empty($action["report"])) {
				return "auto";
			}

			return "server";
		}

		/**
		 * Validate the optional handler method name a module action submits to. It
		 * must be a legal PHP method identifier; the invoke endpoint additionally
		 * checks the module class has opted it in (see ModuleService::invokeAction).
		 */
		private function validateActionHandler($handler) {
			$handler = trim((string)$handler);

			if ($handler === "") {
				return "";
			}

			if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $handler)) {
				throw new BadRequestException(
					"Action handler must be a valid method name.",
					"invalid_handler",
					400
				);
			}

			return $handler;
		}

		/** Pull a non-empty module source from a request body, or 400. */
		private function validateModuleSource(array $body) {
			$source = isset($body["module_source"]) ? (string)$body["module_source"] : "";

			if (trim($source) === "") {
				throw new BadRequestException(
					"A custom (module) action needs its drawing source code.",
					"missing_module_source",
					400
				);
			}

			return $source;
		}

		/**
		 * A single path segment is safe when it's non-empty, contains only
		 * letters / digits / dot / dash / underscore, and has no ".." traversal.
		 * (Routes are already alnum+dash, but this guards the filesystem regardless.)
		 */
		private function safeSegment($segment) {
			return $segment !== ""
				&& (bool)preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $segment)
				&& strpos($segment, "..") === false;
		}

		/**
		 * Split a module record into [extension, local-route]. An extension module's
		 * route is namespaced "{extension}*{local-route}"; a core/custom module has
		 * no extension and the route is used as-is. Falls back to splitting the route
		 * on `*` when the `extension` field is absent.
		 */
		private function moduleRouteParts(array $module) {
			$route = (string)($module["route"] ?? "");
			$extension = (string)($module["extension"] ?? "");

			if ($extension !== "" && strpos($route, $extension . "*") === 0) {
				$route = substr($route, strlen($extension) + 1);
			} elseif ($extension === "" && strpos($route, "*") !== false) {
				[$extension, $route] = explode("*", $route, 2);
			}

			return [$extension, $route];
		}

		/**
		 * Filesystem path of a local module action's source ("" if any segment is
		 * unsafe). Mirrors where legacy custom actions live:
		 *   - extension module → extensions/{extension}/modules/{local}/{action}.js
		 *   - core/custom module → custom/admin/modules/{route}/{action}.js
		 */
		private function actionSourcePath(array $module, $action_route) {
			$action = (string)$action_route;

			if (!$this->safeSegment($action)) {
				return "";
			}

			[$extension, $local] = $this->moduleRouteParts($module);

			if (!$this->safeSegment($local)) {
				return "";
			}

			if ($extension !== "") {
				if (!$this->safeSegment($extension)) {
					return "";
				}

				return SERVER_ROOT . "extensions/$extension/modules/$local/$action.js";
			}

			return SERVER_ROOT . "custom/admin/modules/$local/$action.js";
		}

		/** Write a local module action's source to disk, creating the directory. */
		private function writeActionSource(array $module, $action_route, $source) {
			$path = $this->actionSourcePath($module, $action_route);

			if ($path === "") {
				throw new BadRequestException("Invalid module or action route.", "invalid_route", 400);
			}

			if (!BigTree::putFile($path, $source)) {
				throw new BadRequestException(
					"Could not write the action file — check that its modules directory is writable.",
					"module_write_failed",
					400
				);
			}
		}

		/** Read a local module action's source from disk ("" if none). */
		private function readActionSource(array $module, $action_route) {
			$path = $this->actionSourcePath($module, $action_route);

			if ($path === "") {
				return "";
			}

			return is_file($path) ? (string)file_get_contents($path) : "";
		}

		/** Remove a local module action's source file. */
		private function deleteActionSource(array $module, $action_route) {
			$path = $this->actionSourcePath($module, $action_route);

			if ($path !== "" && is_file($path)) {
				@unlink($path);
			}
		}

		/** Move a module action's source file when its route changes. */
		private function moveActionSource(array $module, $old_route, $new_route) {
			$old_path = $this->actionSourcePath($module, $old_route);
			$new_path = $this->actionSourcePath($module, $new_route);

			if ($old_path !== "" && $new_path !== "" && is_file($old_path) && !is_file($new_path)) {
				BigTree::makeDirectory(dirname($new_path));
				@rename($old_path, $new_path);
			}
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
