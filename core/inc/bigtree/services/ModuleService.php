<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Flag;
	use BigTree\Api\Json;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Services\AI\Tools\ModuleToolBackend;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use BigTree;
	use SQL;

	/**
	 * Modules + module groups. Sub-resources (forms, views, reports, actions) are
	 * exposed read-only in v1; their schemas are complex enough that creation/edit
	 * via API is a follow-on (the developer UI still creates them).
	 */
	class ModuleService implements ModuleToolBackend {
		use ModuleSubResourceSupport;
		use ModuleFormFieldsSupport;

		// The max:255 the POST /module-groups route enforces on the group name
		// (audit #7 B4).
		private const AI_GROUP_NAME_MAX_LENGTH = 255;

		// The embed-form sub-resource write shape: column => transform verb (see
		// ModuleSubResourceSupport::buildInsert). `fields` (cleanFormFields) and
		// `hash` (minted on create) are the per-entity specials and are set by
		// hand in the embed-form methods below.
		private const EMBED_FORM_FIELDS = [
			"title" => "encode",
			"table" => "string",
			"hooks" => "array",
			"default_position" => "string",
			"default_pending" => "checkbox",
			"css" => "string",
			"redirect_url" => "encode",
			"thank_you_message" => "string",
		];

		public function list(Request $request) {
			$rows = BigTreeJSONDB::getAll("modules", "position", "DESC");

			$me = $request->user;
			$visible = array_filter($rows, function ($m) use ($me) {

				return PermissionService::userHasModuleAccess($me, $m["id"], "v");
			});

			$enriched = array_map(function ($m) {

				return $this->present($m, $this->groupName($m));
			}, array_values($visible));

			return Response::ok($enriched);
		}

		public function get(Request $request) {
			$id = $request->routeParam("id");
			$m = Entity::findOrFailJson("modules", $id, "Module");

			if (!PermissionService::userHasModuleAccess($request->user, $id, "v")) {
				throw new \BigTree\Api\Exceptions\AuthorizationException("Module access denied");
			}

			$out = $this->present($m, $this->groupName($m));

			// Caller's access level lets the SPA decide whether to offer
			// "Save & Publish" (publishers only) on this module's forms.
			$out["access"] = PermissionService::userModuleLevel($request->user, $id);

			return Response::ok($out);
		}

		public function create(Request $request) {
			$d = $request->body;
			$route = $d["route"] ?? BigTreeCMS::urlify($d["name"]);

			if (!Sanitize::isValidId((string)$route, 127, "-")) {
				throw new BadRequestException("Module route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route");
			}

			$route = $this->uniqueModuleRoute($route);
			$id = BigTreeJSONDB::insert("modules", $this->moduleInsertMap($d, $d["name"], $route));

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
				throw new BadRequestException("Module name is required", "invalid_name");
			}

			// The table name flows into raw DDL, so it must be a bare identifier.
			if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
				throw new BadRequestException("Table name must contain only letters, numbers, and underscores", "invalid_table");
			}

			if (strlen($table) > 64) {
				throw new BadRequestException("Table name must be 64 characters or fewer", "invalid_table");
			}

			if (BigTree::tableExists($table)) {
				throw new ConflictException("A table named \"$table\" already exists", "table_exists");
			}

			if ($class !== "" && class_exists($class)) {
				throw new ConflictException("A class named \"$class\" already exists", "class_exists");
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

				$base = \BigTree\Api\Sanitize::columnName($title);

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
				throw new BadRequestException("Add at least one field with a title", "no_fields");
			}

			$route = $d["route"] ?? BigTreeCMS::urlify($name);

			if (!Sanitize::isValidId((string)$route, 127, "-")) {
				throw new BadRequestException("Module route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route");
			}

			$route = $this->uniqueModuleRoute($route);
			$actions_in = is_array($d["actions"] ?? null) ? $d["actions"] : [];
			$view_type = ($d["view_type"] ?? "searchable") === "draggable" ? "draggable" : "searchable";

			// — Everything validated; create the module record —
			// Pass validated class/table via $d so the shared insert map picks them up.
			$d["class"] = $class;
			$d["table"] = $table;
			$module_id = BigTreeJSONDB::insert("modules", $this->moduleInsertMap($d, $name, $route));

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

			ModuleViewService::updateModuleViewColumnNumericStatusForTable($table);

			return Response::created($this->present(BigTreeJSONDB::get("modules", $module_id)), null);
		}

		// Insert a module action via the JSONDB subset, mirroring createAction's
		// position handling. Used only by scaffold().
		private function insertScaffoldAction($context, $module_id, $name, $route, $in_nav, $icon, $form, $view, $position) {
			$route = ModuleService::uniqueModuleActionRoute($module_id, (string)$route);

			if ((int)$position === 0) {
				$context->incrementPosition("actions");
			}

			return $context->insert("actions", [
				"route" => $route,
				"in_nav" => Flag::checkbox($in_nav),
				"class" => (string)$icon,
				"name" => BigTree::safeEncode((string)$name),
				"form" => $form ?: null,
				"view" => $view ?: null,
				"report" => null,
				"level" => 0,
				"position" => (int)$position,
			]);
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
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("modules", $id, "Module");
			$d = $request->body;

			$next = array_merge($existing, array_filter([
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : null,
				"group" => array_key_exists("group", $d) ? $d["group"] : null,
				"class" => $d["class"] ?? null,
				"gbp" => $d["gbp"] ?? null,
				"icon" => $d["icon"] ?? null,
			], function ($v) { return $v !== null; }));

			BigTreeJSONDB::update("modules", $id, $next);

			return Response::ok($this->present(BigTreeJSONDB::get("modules", $id)));
		}

		public function delete(Request $request) {
			$id = $request->routeParam("id");
			Entity::assertExistsJson("modules", $id, "Module");

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
			$ids = $request->bodyList("ids", "string");
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
			$module = $this->loadModule($request->routeParam("id"));

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
			$module = $this->loadModule($request->routeParam("id"));
			$action_id = $request->routeParam("sid");
			$action = $this->requireSub($module, "actions", $action_id, "Action");

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

			return Response::ok($payload)->cacheFor(60);
		}

		public function forms(Request $request) {

			return $this->listBucket($request, "forms");
		}

		public function views(Request $request) {

			return $this->listBucket($request, "views");
		}

		public function reports(Request $request) {

			return $this->listBucket($request, "reports");
		}

		public function embedForms(Request $request) {

			return $this->listBucket($request, "embed-forms");
		}

		// — Action CRUD —

		public function createAction(Request $request) {
			[$module_id, $module, $context] = $this->moduleContext($request);
			$d = $request->body;

			$route_raw = (string)($d["route"] ?? "");

			if ($route_raw !== "" && !Sanitize::isValidId($route_raw, 127, "-")) {
				throw new BadRequestException("Action route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route");
			}

			// Auto-generate the route from the name when blank (as the UI promises).
			if ($route_raw === "") {
				$route_raw = BigTreeCMS::urlify((string)($d["name"] ?? ""));
			}

			$route = ModuleService::uniqueModuleActionRoute($module_id, $route_raw);
			$position = (int)($d["position"] ?? 0);

			if ($position === 0) {
				$context->incrementPosition("actions");
			}

			$record = [
				"route" => $route,
				"in_nav" => Flag::checkbox($d["in_nav"] ?? null),
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
			$action_id = $request->routeParam("sid");
			[$module_id, $module, $context] = $this->moduleContext($request);
			$existing = $this->requireSub($module, "actions", $action_id, "Action");

			$d = $request->body;

			$update = [];

			if (isset($d["name"])) {
				$update["name"] = BigTree::safeEncode((string)$d["name"]);
			}

			if (array_key_exists("in_nav", $d)) {
				$update["in_nav"] = Flag::checkbox($d["in_nav"]);
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

				if ($route_raw !== "" && !Sanitize::isValidId($route_raw, 127, "-")) {
					throw new BadRequestException("Action route must be alphanumeric (with -) and ≤ 127 chars", "invalid_route");
				}

				// Auto-generate from the name when explicitly cleared.
				if ($route_raw === "") {
					$route_raw = BigTreeCMS::urlify((string)($d["name"] ?? ($existing["name"] ?? "")));
				}

				$update["route"] = ModuleService::uniqueModuleActionRoute($module_id, $route_raw, $action_id);
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
			$action_id = $request->routeParam("sid");
			[, $module, $context] = $this->moduleContext($request);
			$existing = $this->requireSub($module, "actions", $action_id, "Action");

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
					if ((string)($sib[$rel] ?? "") === (string)$target_id) {
						$still_referenced = true;
						break;
					}
				}

				if (!$still_referenced) {
					$bucket = $rel . "s"; // form → forms, view → views, report → reports
					$context->delete($bucket, $target_id);
				}
			}

			return Response::noContent();
		}

		public function reorderActions(Request $request) {
			[, , $context] = $this->moduleContext($request);
			$ids = $request->bodyList("ids", "string");
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
			$module_id = $request->routeParam("id");
			$action_id = $request->routeParam("sid");
			$module = $this->loadModule($module_id);
			$action = $this->requireSub($module, "actions", $action_id, "Action");

			$handler = $this->validateActionHandler($action["handler"] ?? "");

			if ($handler === "") {
				throw new BadRequestException("This action has no server handler to run.", "no_handler");
			}

			$class = (string)($module["class"] ?? "");

			if ($class === "" || !class_exists($class)) {
				throw new BadRequestException("This module has no class to run the action on.", "missing_module_class");
			}

			$instance = new $class();

			// Whitelist: the class must explicitly opt the handler in. Never dispatch
			// a client-named method that wasn't declared by the module author.
			$allowed = $this->declaredActionHandlers($instance);

			if (!array_key_exists($handler, $allowed)) {
				throw new AuthorizationException("This action's handler is not enabled on the module.", "forbidden_handler");
			}

			// Level gate: the action's own level plus any per-handler minimum.
			$required = max((int)($action["level"] ?? 0), (int)$allowed[$handler]);
			$user_level = (int)($request->user->level ?? 0);

			if ($user_level < $required) {
				throw new AuthorizationException("You don't have access to run this action.");
			}

			if (!method_exists($instance, $handler)) {
				throw new BadRequestException("The action handler \"$handler\" is declared but missing on the module class.", "handler_missing");
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

		// — Embed-form CRUD —

		public function createEmbedForm(Request $request) {
			[$module_id, , $context] = $this->moduleContext($request);
			$d = $request->body;

			$insert = $this->buildInsert($d, self::EMBED_FORM_FIELDS);
			$insert["fields"] = $this->cleanFormFields($d["fields"] ?? []);
			$insert["hash"] = bin2hex(random_bytes(16));

			$id = $context->insert("embed-forms", $insert);

			return Response::created($this->getSubResource($module_id, "embed-forms", $id), null);
		}

		public function updateEmbedForm(Request $request) {
			$ef_id = $request->routeParam("sid");
			[$module_id, $module, $context] = $this->moduleContext($request);
			$this->requireSub($module, "embed-forms", $ef_id, "Embed form");

			$d = $request->body;
			$update = $this->buildUpdate($d, self::EMBED_FORM_FIELDS);

			if (isset($d["fields"])) {
				$update["fields"] = $this->cleanFormFields($d["fields"]);
			}

			if ($update) {
				$context->update("embed-forms", $ef_id, $update);
			}

			return Response::ok($this->getSubResource($module_id, "embed-forms", $ef_id));
		}

		public function deleteEmbedForm(Request $request) {
			$ef_id = $request->routeParam("sid");
			[, $module, $context] = $this->moduleContext($request);
			$this->requireSub($module, "embed-forms", $ef_id, "Embed form");

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
			$form = $this->requireEmbedFormByHash($request);

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
		 * hook (if any), processes each field via FieldProcessingService::processField to
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
			$form = $this->requireEmbedFormByHash($request);

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
			ResourceAllocationService::$IRLsCreated = [];

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

				$output = \BigTree\Services\FieldProcessingService::processField($field);

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
			\BigTree\Services\ResourceAllocationService::allocateResources($form["table"], $pending ? "p".$edit_id : $edit_id);

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
				throw new BadRequestException("Action handler must be a valid method name.", "invalid_handler");
			}

			return $handler;
		}

		/** Pull a non-empty module source from a request body, or 400. */
		private function validateModuleSource(array $body) {
			$source = isset($body["module_source"]) ? (string)$body["module_source"] : "";

			if (trim($source) === "") {
				throw new BadRequestException("A custom (module) action needs its drawing source code.", "missing_module_source");
			}

			return $source;
		}

		/**
		 * Lazily-built collaborator that owns the action-source filesystem codegen
		 * (read/write/move/delete of a local action's on-disk drawing source). The
		 * concern was extracted from this class to keep filesystem IO out of CRUD;
		 * these wrappers preserve the former private-method call sites verbatim.
		 */
		private $action_source_service = null;

		private function actionSourceService(): ModuleActionSourceService {
			if ($this->action_source_service === null) {
				$this->action_source_service = new ModuleActionSourceService();
			}

			return $this->action_source_service;
		}

		private function writeActionSource(array $module, $action_route, $source) {

			$this->actionSourceService()->writeActionSource($module, $action_route, $source);
		}

		private function readActionSource(array $module, $action_route) {

			return $this->actionSourceService()->readActionSource($module, $action_route);
		}

		private function deleteActionSource(array $module, $action_route) {

			$this->actionSourceService()->deleteActionSource($module, $action_route);
		}

		private function moveActionSource(array $module, $old_route, $new_route) {

			$this->actionSourceService()->moveActionSource($module, $old_route, $new_route);
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
		// — groups —

		public function listGroups(Request $request) {

			return Response::ok(BigTreeJSONDB::getAll("module-groups", "position", "DESC"));
		}

		public function getGroup(Request $request) {
			$id = $request->routeParam("id");
			$g = Entity::findOrFailJson("module-groups", $id, "Module group");

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
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("module-groups", $id, "Module group");
			$d = $request->body;
			BigTreeJSONDB::update("module-groups", $id, array_merge($existing, array_filter([
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : null,
				"route" => $d["route"] ?? null,
			], function ($v) { return $v !== null; })));

			return Response::ok(BigTreeJSONDB::get("module-groups", $id));
		}

		public function deleteGroup(Request $request) {
			$id = $request->routeParam("id");
			Entity::assertExistsJson("module-groups", $id, "Module group");

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

		/**
		 * Shared 8-key insert map for create() and scaffold(). $name and $route
		 * are already validated/normalized by the caller; class/table/group/icon/gbp
		 * are read from $d with the same defaults both paths previously inlined.
		 */
		// — AI tool seam (ModuleToolBackend) —
		//
		// Developer-only two-phase creation of a bare module record (no table DDL),
		// reusing the same route validation + insert map as create(). Developer level
		// is re-checked at validation and approval.

		/**
		 * Read one module's definition and, importantly, what it's still missing.
		 *
		 * The assistant previously had no way to see a module's shape — it discovered
		 * fields only by triggering an error, and had no way at all to tell that a
		 * module it had just created was an unusable shell. This makes both visible.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetModule(string $module_id, $user): array {
			if (trim($module_id) === "") {

				return ["error" => "A module_id is required."];
			}

			$module = BigTreeJSONDB::get("modules", $module_id);

			if (!$module) {
				$module = BigTreeJSONDB::get("modules", $module_id, "route");
			}

			if (!$module) {

				return ["error" => "Module \"{$module_id}\" does not exist."];
			}

			if (PermissionService::userModuleLevel($user, (string)$module["id"]) === "n") {

				return ["denied" => "You do not have access to this module."];
			}

			$forms = is_array($module["forms"] ?? null) ? $module["forms"] : [];
			$views = is_array($module["views"] ?? null) ? $module["views"] : [];
			$actions = is_array($module["actions"] ?? null) ? $module["actions"] : [];
			$table = (string)($module["table"] ?? "");

			if ($table === "" && $forms) {
				$table = (string)($forms[0]["table"] ?? "");
			}

			$missing = [];

			if ($table === "") {
				$missing[] = "a database table";
			}

			if (!$forms) {
				$missing[] = "an entry form";
			}

			if (!$views) {
				$missing[] = "a view (landing screen)";
			}

			if (!$actions) {
				$missing[] = "at least one action";
			}

			// A module nobody holds a grant on is reachable only by administrators and
			// developers, so "everything is configured" is still not "anyone can use
			// it" — the checklist said nothing about it, and neither did the create.
			if (!$this->moduleHasAnyGrant((string)$module["id"])) {
				$missing[] = "a permission grant for at least one user";
			}

			$gbp = is_array($module["gbp"] ?? null) ? $module["gbp"] : [];

			return ["module" => [
				"id" => (string)$module["id"],
				"name" => (string)($module["name"] ?? $module["id"]),
				"route" => (string)($module["route"] ?? ""),
				"group" => $this->groupName($module),
				"class" => (string)($module["class"] ?? ""),
				"icon" => (string)($module["icon"] ?? ""),
				"table" => $table,
				"forms" => array_map(function (array $f): array {

					return [
						"id" => (string)($f["id"] ?? ""),
						"title" => (string)($f["title"] ?? ""),
						"table" => (string)($f["table"] ?? ""),
						"field_count" => is_array($f["fields"] ?? null) ? count($f["fields"]) : 0,
					];
				}, array_values($forms)),
				"views" => array_map(function (array $v): array {

					return [
						"id" => (string)($v["id"] ?? ""),
						"title" => (string)($v["title"] ?? ""),
						"table" => (string)($v["table"] ?? ""),
					];
				}, array_values($views)),
				"action_count" => count($actions),
				"gbp" => [
					"enabled" => !empty($gbp["enabled"]),
					"group_column" => !empty($gbp["enabled"]) ? (string)($gbp["group_field"] ?? "") : "",
				],
				"your_access_level" => PermissionService::userModuleLevel($user, (string)$module["id"]),
				"is_complete" => !$missing,
				"missing_setup" => $missing,
			]];
		}

		/**
		 * Validate an edit to an existing module's presentation: name, group, icon.
		 *
		 * Deliberately narrow. Route is excluded because changing it breaks every
		 * bookmarked admin URL and any hard-coded link, and `class`/`table`/forms/
		 * views/actions belong to the Module Designer, whose changes are DDL. Renaming
		 * or regrouping a module is the plain-text edit developers actually ask for.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateModuleUpdate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can edit modules."];
			}

			$module_id = trim((string)($args["module_id"] ?? ""));
			$module = $module_id !== "" ? BigTreeJSONDB::get("modules", $module_id) : null;

			if (!$module) {
				$module = $module_id !== "" ? BigTreeJSONDB::get("modules", $module_id, "route") : null;
			}

			if (!$module) {

				return ["error" => "Module \"{$module_id}\" does not exist."];
			}

			$changes = [];
			$diff = [];

			if (array_key_exists("name", $args)) {
				$name = trim((string)$args["name"]);

				if ($name === "") {

					return ["error" => "A module's name can't be empty."];
				}

				if ($name !== (string)($module["name"] ?? "")) {
					$changes["name"] = $name;
					$diff["name"] = ["from" => (string)($module["name"] ?? ""), "to" => $name];
				}
			}

			if (array_key_exists("group", $args)) {
				$resolved_group = $this->aiResolveModuleGroup($args["group"]);

				if (isset($resolved_group["needs_input"])) {

					return $resolved_group;
				}

				$group = $resolved_group["id"];

				if ($group !== (string)($module["group"] ?? "")) {
					$changes["group"] = $group !== "" ? $group : null;
					$diff["group"] = [
						"from" => (string)($module["group"] ?? "") ?: "(ungrouped)",
						"to" => $group ?: "(ungrouped)",
					];
				}
			}

			if (array_key_exists("icon", $args)) {
				$icon = trim((string)$args["icon"]);

				if ($icon !== (string)($module["icon"] ?? "")) {
					$changes["icon"] = $icon;
					$diff["icon"] = ["from" => (string)($module["icon"] ?? ""), "to" => $icon];
				}
			}

			if (!$changes) {

				return ["error" => "No changes were supplied — nothing to update."];
			}

			$name = (string)($module["name"] ?? $module["id"]);

			return [
				"ok" => true,
				"summary" => "Update module “{$name}”. Its route, table, forms and views are unchanged.",
				"preview" => [
					"action" => "update_module",
					"id" => (string)$module["id"],
					"name" => $name,
					"changes" => $diff,
				],
				"payload" => [
					"id" => (string)$module["id"],
					"changes" => $changes,
				],
				"fingerprint" => ["type" => "json_record", "store" => "modules", "id" => (string)$module["id"]],
			];
		}

		/**
		 * Execute an approved module edit. Re-checks developer level and existence.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdateModule(array $payload, $user): array {
			if (PermissionService::level($user) < 2) {
				throw new AuthorizationException("Only developers can edit modules.");
			}

			$id = (string)($payload["id"] ?? "");
			$module = $id !== "" ? BigTreeJSONDB::get("modules", $id) : null;

			if (!$module) {

				return ["mode" => "error", "message" => "That module no longer exists."];
			}

			$changes = is_array($payload["changes"] ?? null) ? $payload["changes"] : [];

			// A group can be removed by storing null, so the key's presence is what
			// matters, not its truthiness.
			foreach (["name", "group", "icon"] as $field) {
				if (array_key_exists($field, $changes)) {
					$module[$field] = $field === "group" ? $changes[$field] : BigTree::safeEncode((string)$changes[$field]);
				}
			}

			BigTreeJSONDB::update("modules", $id, $module);

			return [
				"mode" => "updated",
				"id" => $id,
				"name" => (string)($module["name"] ?? $id),
			];
		}

		/**
		 * Validate creating a module record. Developer-only.
		 *
		 * This creates the module *record* alone — no table, forms, views or actions,
		 * all of which are the Module Designer's DDL-bearing work. The proposal says
		 * so, and the result carries the remaining setup steps, because an approved
		 * create otherwise leaves something that looks finished in the nav and
		 * dead-ends everywhere else.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateModuleCreate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can create modules."];
			}

			$name = trim((string)($args["name"] ?? ""));

			if ($name === "") {

				return ["error" => "A module name is required."];
			}

			$route = trim((string)($args["route"] ?? "")) ?: BigTreeCMS::urlify($name);

			if (!Sanitize::isValidId((string)$route, 127, "-")) {

				return ["error" => "The module route must be alphanumeric (dashes allowed) and ≤ 127 characters."];
			}

			$route = $this->uniqueModuleRoute($route);
			$resolved_group = $this->aiResolveModuleGroup($args["group"] ?? "");

			if (isset($resolved_group["needs_input"])) {

				return $resolved_group;
			}

			$group = $resolved_group["id"];

			$class = trim((string)($args["class"] ?? ""));

			// A typo'd class name used to be stored verbatim, leaving a module wired to
			// a class that doesn't exist. The class is created by the Module Designer,
			// not here, so the only valid values are "" or a class that already exists.
			if ($class !== "" && !$this->moduleClassExists($class)) {

				return ["error" => "\"{$class}\" isn't available as a module class — it either doesn't exist, or "
					. "another module already uses it. Leave `class` empty — the Module Designer creates the class "
					. "when you add a table and forms to the module."];
			}

			$payload = [
				"name" => $name,
				"route" => $route,
				"group" => $group !== "" ? $group : null,
				"class" => $class,
				"icon" => trim((string)($args["icon"] ?? "")),
			];

			return [
				"ok" => true,
				"summary" => "Create a new module “{$name}” (route {$route}). This creates the module record only — "
					. "it will appear in the admin navigation but have no database table, forms, views or actions "
					. "until they're added in the Module Designer.",
				"preview" => [
					"action" => "create_module",
					"name" => $name,
					"route" => $route,
					"group" => $group,
					"class" => $payload["class"],
					"icon" => $payload["icon"],
					"remaining_setup" => $this->aiModuleSetupSteps($name),
				],
				"payload" => $payload,
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreateModule(array $payload, $user): array {
			if (PermissionService::level($user) < 2) {
				throw new AuthorizationException("Only developers can create modules.");
			}

			$name = trim((string)($payload["name"] ?? ""));
			$route = trim((string)($payload["route"] ?? ""));

			if ($name === "" || $route === "" || !Sanitize::isValidId($route, 127, "-")) {

				return ["mode" => "error", "message" => "That module can no longer be created."];
			}

			// Re-asked at approval like every other staged value: a class free at
			// staging can be claimed by another module inside the proposal's 24h life.
			$class = (string)($payload["class"] ?? "");

			if ($class !== "" && !$this->moduleClassExists($class)) {

				return ["mode" => "error", "message" => "The class \"{$class}\" is no longer available — it either "
					. "doesn't exist or another module has claimed it since this was proposed."];
			}

			// Re-derive a unique route in case one was taken since validation.
			$route = $this->uniqueModuleRoute($route);
			$id = BigTreeJSONDB::insert("modules", $this->moduleInsertMap([
				"group" => $payload["group"] ?? null,
				"class" => $class,
				"table" => "",
				"icon" => (string)($payload["icon"] ?? ""),
			], $name, $route));

			return [
				"mode" => "created",
				"id" => (string)$id,
				"name" => $name,
				"route" => $route,
				"is_complete" => false,
				"remaining_setup" => $this->aiModuleSetupSteps($name),
				"note" => "“{$name}” now appears in the admin navigation but is not usable yet — it has no database "
					. "table, so it has no landing view and entries can't be added to it (including by the "
					. "assistant). Finish it in Developer → Modules → Module Designer.",
			];
		}

		/**
		 * Resolve a requested module group by id or name.
		 *
		 * A group the model named but couldn't identify was a flat "does not exist"
		 * error with no way forward — there was no path to create one and no decline
		 * line saying so, so the model rediscovered the wall by failing. Hand back the
		 * choice instead.
		 *
		 * @param mixed $requested
		 * @return array{id:string,name:string}|array{needs_input:array<string,mixed>}
		 */
		private function aiResolveModuleGroup($requested): array {
			$requested = trim((string)$requested);

			if ($requested === "") {

				return ["id" => "", "name" => ""];
			}

			$groups = BigTreeJSONDB::getAll("module-groups");

			foreach ($groups as $group) {
				$id = (string)($group["id"] ?? "");
				$name = (string)($group["name"] ?? "");

				if ($id === $requested || strcasecmp($name, $requested) === 0) {

					return ["id" => $id, "name" => $name !== "" ? $name : $id];
				}
			}

			$options = array_map(function (array $group): array {

				return [
					"id" => (string)($group["id"] ?? ""),
					"label" => (string)($group["name"] ?? $group["id"] ?? ""),
					"description" => "Existing module group",
				];
			}, $groups);

			$options[] = [
				"id" => "",
				"label" => "No group",
				"description" => "Leave the module ungrouped in the admin navigation",
			];

			return ["needs_input" => [
				"question" => "There's no module group called “{$requested}”. Which group should this module go in? "
					. "(create_module_group can make a new one.)",
				"options" => $options,
			]];
		}

		/**
		 * Validate creating a module group. Developer-only, matching the module writes.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateModuleGroupCreate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can create module groups."];
			}

			$name = trim((string)($args["name"] ?? ""));

			if ($name === "") {

				return ["error" => "A module group name is required (e.g. \"Content\")."];
			}

			if (mb_strlen($name) > self::AI_GROUP_NAME_MAX_LENGTH) {

				return ["error" => "The module group name is " . mb_strlen($name) . " characters, but the field holds "
					. "at most " . self::AI_GROUP_NAME_MAX_LENGTH . ". Shorten it and try again."];
			}

			foreach (BigTreeJSONDB::getAll("module-groups") as $group) {
				if (strcasecmp((string)($group["name"] ?? ""), $name) === 0) {

					return ["error" => "A module group called “{$name}” already exists."];
				}
			}

			return [
				"ok" => true,
				"summary" => "Create a new module group “{$name}”. It starts empty — modules are moved into it with "
					. "update_module.",
				"preview" => [
					"action" => "create_module_group",
					"name" => $name,
				],
				"payload" => ["name" => $name],
			];
		}

		/**
		 * Execute an approved module group creation. Re-checks developer level and
		 * that the name is still free.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreateModuleGroup(array $payload, $user): array {
			if (PermissionService::level($user) < 2) {
				throw new AuthorizationException("Only developers can create module groups.");
			}

			$name = trim((string)($payload["name"] ?? ""));

			if ($name === "" || mb_strlen($name) > self::AI_GROUP_NAME_MAX_LENGTH) {

				return ["mode" => "error", "message" => "That module group can no longer be created."];
			}

			foreach (BigTreeJSONDB::getAll("module-groups") as $group) {
				if (strcasecmp((string)($group["name"] ?? ""), $name) === 0) {

					return ["mode" => "error", "message" => "A module group called “{$name}” already exists."];
				}
			}

			$id = BigTreeJSONDB::insert("module-groups", [
				"name" => BigTree::safeEncode($name),
				// The name is unique but its slug need not be — "Site Content" and
				// "Site  Content" both urlify to "site-content" and would collide on the
				// route the front-end resolves groups by. De-duplicate it the same way
				// module routes are (audit #7 B5).
				"route" => $this->uniqueModuleGroupRoute(BigTreeCMS::urlify($name)),
				"position" => 0,
			]);

			return ["mode" => "created", "id" => (string)$id, "name" => $name];
		}

		/**
		 * Whether any user holds a module-level or group-level grant on this module.
		 * Administrators and developers see every module regardless, so this is about
		 * whether the module is reachable by the editors it was built for.
		 */
		private function moduleHasAnyGrant(string $module_id): bool {
			foreach (SQL::fetchAllSingle("SELECT permissions FROM bigtree_users WHERE level = 0") as $stored) {
				$permissions = Json::decode($stored);

				if (!empty($permissions["module"][$module_id]) || !empty($permissions["module_gbp"][$module_id])) {

					return true;
				}
			}

			return false;
		}

		/**
		 * The concrete setup a bare module record still needs before it works. The
		 * proposal summary warned that no table is created, but nothing told the user
		 * (or the model) what "finish it" actually means — so an approved create left
		 * a module that looks finished in the nav and dead-ends everywhere else.
		 *
		 * @return list<string>
		 */
		private function aiModuleSetupSteps(string $name): array {

			return [
				"Create the database table that will hold “{$name}” entries (Module Designer → Table).",
				"Add an entry form so editors can create and edit entries.",
				"Add a view so the module has a landing screen listing its entries.",
				"Add the module's actions (at minimum a default 'view' action) so it's reachable from the nav.",
				"Grant users access to it — a module nobody holds a permission on is invisible to everyone "
					. "except administrators and developers (Users → a user → Module Permissions).",
				"Add the module's class if it needs one (Module Designer → Class), so custom logic and the "
					. "module's own API have somewhere to live.",
				// The assistant deliberately has no `gbp` argument: enabling group-based
				// permissions is several interdependent choices (which column groups
				// entries, which table supplies the groups, how each is titled) and
				// getting it wrong hides every existing entry from every scoped editor.
				// Naming it here is the honest version — the user is told the option
				// exists and where it lives, rather than being offered a write that
				// can't be previewed.
				"Decide whether entries should be scoped per editor group (Module Designer → Group Based "
					. "Permissions). The assistant can't configure this — but once it's on, every entry needs a "
					. "value in the group column or it's invisible to the editors it belongs to.",
			];
		}

		/**
		 * Whether a module class name is usable for the module being created or
		 * edited: a loadable PHP class that no *other* module has already claimed.
		 *
		 * A class already in use is the opposite of valid — two modules sharing one
		 * class means the second one's entries route through the first one's logic —
		 * and the Module Designer refuses it for exactly that reason. This used to
		 * treat that case as proof the class existed, so a claimed class was the one
		 * thing guaranteed to pass.
		 *
		 * @param string $except_module_id Module allowed to already hold the class.
		 */
		private function moduleClassExists(string $class, string $except_module_id = ""): bool {
			foreach (BigTreeJSONDB::getAll("modules") as $module) {
				if ((string)($module["class"] ?? "") !== $class) {

					continue;
				}

				if ((string)($module["id"] ?? "") !== $except_module_id) {

					return false;
				}
			}

			return class_exists($class);
		}

		private function moduleInsertMap(array $d, string $name, string $route): array {

			return [
				"name" => BigTree::safeEncode($name),
				"group" => $d["group"] ?? null,
				"class" => $d["class"] ?? "",
				"table" => $d["table"] ?? "",
				"gbp" => $d["gbp"] ?? ["enabled" => false],
				"icon" => $d["icon"] ?? "",
				"route" => $route,
				"position" => 0,
			];
		}

		private function groupName(array $m): string {
			if (empty($m["group"])) {
				return "";
			}

			$g = BigTreeJSONDB::get("module-groups", $m["group"]);

			return $g ? $g["name"] : "";
		}

		private function requireEmbedFormByHash(Request $request): array {
			$hash = $request->routeParam("hash");

			if ($hash === "") {
				throw new NotFoundException("Embed form not found");
			}

			$form = \BigTreeAutoModule::getEmbedFormByHash($hash);

			if (!$form) {
				throw new NotFoundException("Embed form not found");
			}

			return $form;
		}

		private function uniqueModuleRoute($base) {
			$route = $base;
			$x = 2;

			while (BigTreeJSONDB::get("modules", $route, "route")) {
				$route = $base . "-" . $x++;
			}

			return $route;
		}

		/** The module-group equivalent of uniqueModuleRoute (audit #7 B5). */
		private function uniqueModuleGroupRoute($base) {
			$route = $base !== "" ? $base : "group";
			$x = 2;

			while (BigTreeJSONDB::get("module-groups", $route, "route")) {
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
			];
		}
	
		public static function getModule($id) {
			$module = BigTreeJSONDB::get("modules", $id);

			if (empty($module)) {
				return null;
			}

			if (empty($module["actions"]) || !is_array($module["actions"])) {
				$module["actions"] = [];
			}

			if (empty($module["views"]) || !is_array($module["views"])) {
				$module["views"] = [];
			}

			if (empty($module["forms"]) || !is_array($module["forms"])) {
				$module["forms"] = [];
			}

			if (empty($module["embeddable-forms"]) || !is_array($module["embeddable-forms"])) {
				$module["embeddable-forms"] = [];
			}

			if (empty($module["reports"]) || !is_array($module["reports"])) {
				$module["reports"] = [];
			}

			return $module;
		}

		public static function getModuleGroup($id) {
			return BigTreeJSONDB::get("module-groups", $id);
		}

		public static function getModuleActions($module) {
			if (is_array($module)) {
				$module = $module["id"];
			}

			$context = BigTreeJSONDB::getSubset("modules", $module);
			$actions = $context->getAll("actions", "position");

			foreach ($actions as $index => $action) {
				$actions[$index]["module"] = $module;
			}

			return $actions;
		}

		public static function uniqueModuleActionRoute($module, $route, $action = false) {
			$module = BigTreeJSONDB::get("modules", $module);
			$oroute = $route;
			$x = 2;

			do {
				$exists = false;

				foreach ($module["actions"] as $module_action) {
					if ($module_action["id"] != $action && $module_action["route"] == $route) {
						$exists = true;
						$route = $oroute."-".$x;
						$x++;
					}
				}
			} while ($exists);

			return $route;
		}

	}
