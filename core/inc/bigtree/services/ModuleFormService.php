<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree;
	use SQL;
	use BigTreeJSONDB;

	/**
	 * Module forms: CRUD over the form sub-resources stored in a module's JSONDB
	 * record, plus the relation-options and list-options lookups that power the
	 * SPA's relation pickers and dynamic list/select fields.
	 *
	 * Extracted from ModuleService (god-object decomposition, plan 029 — the Form
	 * cluster, the last sub-resource designer, following 027's Report and 028's
	 * View clusters). Behavior is identical to the former ModuleService methods;
	 * this is a pure relocation. Sub-resource resolution
	 * (loadModule/findSub/getSubResource) is shared with ModuleService via
	 * ModuleSubResourceSupport; form-field normalization (cleanFormFields) is
	 * shared via ModuleFormFieldsSupport (also used by ModuleService's scaffold +
	 * embed-form methods, which stay). The Kernel instantiates this service per
	 * request, so the form routes dispatch to it exactly as they did to
	 * ModuleService.
	 */
	class ModuleFormService {
		use ModuleSubResourceSupport;
		use ModuleFormFieldsSupport;

		// The form sub-resource write shape: column => transform verb (see
		// ModuleSubResourceSupport::buildInsert). `fields` is the per-entity
		// special (cleanFormFields) and is set by hand in each method below.
		private const FIELDS = [
			"title" => "encode",
			"table" => "string",
			"default_position" => "string",
			"return_view" => "nullable",
			"return_url" => "encode",
			"tagging" => "checkbox",
			"open_graph" => "checkbox",
			"hooks" => "array",
		];

		// — Form CRUD —

		public function createForm(Request $request) {
			[$module_id, , $context] = $this->moduleContext($request);
			$d = $request->body;

			$insert = $this->buildInsert($d, self::FIELDS);
			$insert["fields"] = $this->cleanFormFields($d["fields"] ?? []);

			$id = $context->insert("forms", $insert);
			$this->syncNumericStatus((string)($d["table"] ?? ""));

			return Response::created($this->getSubResource($module_id, "forms", $id), null);
		}

		public function updateForm(Request $request) {
			$form_id = $request->routeParam("sid");
			[$module_id, $module, $context] = $this->moduleContext($request);
			$existing = $this->requireSub($module, "forms", $form_id, "Form");

			$d = $request->body;
			$update = $this->buildUpdate($d, self::FIELDS);

			if (isset($d["fields"])) {
				$update["fields"] = $this->cleanFormFields($d["fields"]);
			}

			if ($update) {
				$context->update("forms", $form_id, $update);
				$this->syncNumericStatus((string)($update["table"] ?? ($existing["table"] ?? "")));
			}

			// If the title changed and this form is referenced by an add/edit action,
			// keep the action label in sync (matches legacy updateModuleForm).
			if (isset($update["title"])) {
				$title = (string)$d["title"];

				foreach ($module["actions"] ?? [] as $action) {
					if ((string)($action["form"] ?? "") !== (string)$form_id) {
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

			return $this->deleteSubCascade($request, "forms", "form", "Form");
		}

		/**
		 * The shared prologue for relationOptions and listOptions: require the
		 * `column` query param, load the module + its form (sub-resource
		 * ownership), then locate the matching field in the form. Returns
		 * [$module, $form, $field, $column]; each caller applies its own type
		 * check (relation vs list). Preserves the SPA-matched error contract
		 * (missing_column / the "Field … not found" 404).
		 */
		private function requireFormField(Request $request): array {
			$module_id = $request->routeParam("id");
			$form_id = $request->routeParam("sid");
			$column = $request->queryString("column", "", false);

			if ($column === "") {
				throw new BadRequestException("`column` query param is required", "missing_column");
			}

			$module = $this->loadModule($module_id);
			$form = $this->requireSub($module, "forms", $form_id, "Form");

			$field = null;

			foreach ((array)($form["fields"] ?? []) as $candidate) {
				if (($candidate["column"] ?? "") === $column) {
					$field = $candidate;

					break;
				}
			}

			if (!$field) {
				throw new NotFoundException("Field `$column` not found in form $form_id");
			}

			return [$module, $form, $field, $column];
		}

		public function relationOptions(Request $request) {
			[, , $field, $column] = $this->requireFormField($request);

			return Response::ok($this->relationOptionsFor($field, $column, [
				"entry" => $request->queryString("entry"),
				"q" => $request->queryString("q"),
				"ids" => $request->queryString("ids", "", false),
			]));
		}

		/**
		 * The relation lookup itself, with the Request lifted out (audit #12 B1).
		 *
		 * Both callers need exactly this: the SPA's relation picker, and the
		 * assistant's get_relation_options — which exists because audit #11 B2 made
		 * relation fields *writable* by entry id while nothing in the catalogue could
		 * enumerate the ids. A relation's target is `settings["table"]` /
		 * `settings["mtm-other-table"]`, which is usually a plain lookup table no
		 * module owns, so list_module_entries could never reach it.
		 *
		 * The identifiers here come from the field definition and are validated against
		 * a real describeTable before any of them reaches SQL — the same reason
		 * RelationDomain builds its descriptor from the field rather than the model.
		 *
		 * @param array<string,mixed> $field  The form field, as stored.
		 * @param string $column              Its column, for error wording.
		 * @param array<string,mixed> $params entry / q / ids in the query string's shape,
		 *                                    plus an optional limit / offset window.
		 * @return array{items:list<array{id:int,title:string}>,relation:array{type:string,sortable:bool}}
		 */
		public function relationOptionsFor(array $field, string $column, array $params): array {
			$type = (string)($field["type"] ?? "");

			if ($type !== "one-to-many" && $type !== "many-to-many") {
				throw new BadRequestException("Field `$column` is type `$type`, not a relation field", "invalid_field_type");
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

			$schema = $this->requireDescribedTable($table, $descriptor, $column);

			if (empty($schema["columns"][$descriptor])) {
				throw new BadRequestException("Descriptor column `$descriptor` does not exist on `$table`", "invalid_field_settings");
			}

			// Sort clause — validate against the column list so we never
			// concatenate user-controlled text into the SQL. Default to the
			// descriptor when the configured sort references a missing column.
			$order_by = \BigTree\Api\Sanitize::orderClause($sort, $schema["columns"], $descriptor);

			$entry_raw = (string)($params["entry"] ?? "");

			// "Currently linked" mode (MTM only). Query the connecting table to
			// discover which `other-id`s the entry already has — preserves the
			// stored position when the connecting table is sortable.
			if ($entry_raw !== "") {
				if ($type !== "many-to-many") {
					throw new BadRequestException("`?entry=` is only valid for many-to-many fields", "invalid_request");
				}

				if (!ctype_digit($entry_raw)) {
					throw new BadRequestException("`entry` must be a positive integer", "invalid_request");
				}

				$entry_id = (int)$entry_raw;
				$my_id_col = (string)($settings["mtm-my-id"] ?? "");
				$other_id_col = (string)($settings["mtm-other-id"] ?? "");

				if ($my_id_col === "" || $other_id_col === "" || $conn_table === "") {
					throw new BadRequestException("Field `$column` is missing connecting-table settings", "invalid_field_settings");
				}

				$conn_schema = BigTree::describeTable($conn_table);

				if (!$conn_schema || empty($conn_schema["columns"][$my_id_col]) || empty($conn_schema["columns"][$other_id_col])) {
					throw new BadRequestException("Connecting-table columns missing for field `$column`", "invalid_field_settings");
				}

				$conn_order = $sortable ? "`position` DESC" : "`id` ASC";
				$other_ids = SQL::fetchAllSingle(
					"SELECT `$other_id_col` FROM `$conn_table` WHERE `$my_id_col` = ? ORDER BY $conn_order",
					$entry_id
				) ?: [];

				if (empty($other_ids)) {

					return ["items" => [], "relation" => ["type" => $type, "sortable" => $sortable]];
				}

				$other_ids = array_values(array_map("intval", $other_ids));
				$placeholders = \BigTree\Api\Sanitize::placeholders($other_ids);
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

				return ["items" => $items, "relation" => ["type" => $type, "sortable" => $sortable]];
			}

			$where = [];
			$bindings = [];

			$q = (string)($params["q"] ?? "");

			if ($q !== "") {
				$where[] = "`$descriptor` LIKE ?";
				$bindings[] = "%" . $q . "%";
			}

			$ids_raw = (string)($params["ids"] ?? "");

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

					return ["items" => [], "relation" => ["type" => $type, "sortable" => $sortable]];
				}

				$placeholders = \BigTree\Api\Sanitize::placeholders($ids);
				$where[] = "`id` IN ($placeholders)";

				foreach ($ids as $id) {
					$bindings[] = $id;
				}
			}

			$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
			// The picker takes the whole first page of a lookup table; a caller that
			// pages (the assistant, which has to fit its window into a tool result)
			// asks for its own. Cast rather than bound because LIMIT takes no
			// placeholder in this driver — and an int cast is exactly as safe.
			$limit = $ids_raw !== "" ? "" : "LIMIT 250";

			if ($ids_raw === "" && isset($params["limit"])) {
				$limit = "LIMIT " . max(0, (int)($params["offset"] ?? 0)) . ", " . max(1, (int)$params["limit"]);
			}

			$sql = "SELECT `id`, `$descriptor` AS `title` FROM `$table` $where_sql ORDER BY $order_by $limit";
			$rows = SQL::fetchAll($sql, ...$bindings);

			$items = array_map(function ($row) {

				return [
					"id" => (int)$row["id"],
					"title" => (string)($row["title"] ?? ""),
				];
			}, $rows ?: []);

			return [
				"items" => $items,
				"relation" => [
					"type" => $type,
					"sortable" => $sortable,
				],
			];
		}

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
			[, , $field, $column] = $this->requireFormField($request);

			if ((string)($field["type"] ?? "") !== "list") {
				throw new BadRequestException("Field `$column` is not a list field", "invalid_field_type");
			}

			return Response::ok(["options" => $this->resolveListOptions($field, $column)]);
		}

		/**
		 * The option set behind a `list` field, whatever populates it (static list,
		 * database table, state or country).
		 *
		 * Request-free so the AI schema seams can publish the same options the admin
		 * offers, and so the entry sift can reject a value that isn't one of them —
		 * a `db`-populated list stores a *foreign row id*, so a model writing
		 * "Portland" sifted clean and then resolved to blank in every view.
		 *
		 * @param array<string,mixed> $field  A form field definition.
		 * @return list<array{value:string,label:string}>
		 */
		public function resolveListOptions(array $field, string $column): array {
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

			return $options;
		}

		/** Run the validated SELECT behind a db-populated list. */
		private function resolveDatabaseList(array $settings, string $column): array {
			$table = (string)($settings["pop-table"] ?? "");
			$descriptor = (string)($settings["pop-description"] ?? "");
			$sort = (string)($settings["pop-sort"] ?? "");

			$schema = $this->requireDescribedTable($table, $descriptor, $column);

			if (empty($schema["columns"][$descriptor]) || empty($schema["columns"]["id"])) {
				throw new BadRequestException("Field `$column` references columns that don't exist on `$table`", "invalid_field_settings");
			}

			$order_by = \BigTree\Api\Sanitize::orderClause($sort, $schema["columns"], $descriptor);
			$options = [];
			$query = \sqlquery("SELECT `id`, `$descriptor` AS `__label` FROM `$table` ORDER BY $order_by");

			while ($row = \sqlfetch($query)) {
				$options[] = ["value" => (string)$row["id"], "label" => (string)$row["__label"]];
			}

			return $options;
		}

		/**
		 * Shared prefix of relationOptions / resolveDatabaseList: require non-empty
		 * table+descriptor settings, describe the table, 404 if missing. Each caller
		 * keeps its own final column-existence check (the error strings differ).
		 */
		private function requireDescribedTable(string $table, string $descriptor, string $column): array {
			if ($table === "" || $descriptor === "") {
				throw new BadRequestException("Field `$column` is missing table/descriptor settings", "invalid_field_settings");
			}

			$schema = BigTree::describeTable($table);

			if (!$schema || empty($schema["columns"])) {
				throw new NotFoundException("Table `$table` not found");
			}

			return $schema;
		}
	
		public static function getModuleForms($sort = "title", $module = false) {
			$sort_parts = explode(" ", $sort);
			$sort_column = $sort_parts[0] ?? "";
			$sort_direction = $sort_parts[1] ?? "";

			if ($module) {
				$context = BigTreeJSONDB::getSubset("modules", $module);

				return $context->getAll("forms", $sort_column, $sort_direction);
			} else {
				$forms = [];
				$sort_field = [];
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					if (!empty($module["forms"])) {
						$forms = array_merge($forms, array_filter((array) $module["forms"]));
					}
				}

				foreach ($forms as $form) {
					$sort_field[] = $form[$sort_column];
				}

				if ($sort_direction == "DESC") {
					array_multisort($sort_field, SORT_DESC, $forms);
				} else {
					array_multisort($sort_field, SORT_ASC, $forms);
				}

				return $forms;
			}
		}

		public static function getModuleActionForForm($form) {
			if (is_array($form)) {
				$form = $form["id"];
			}

			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				$matching_actions = array_filter($module["actions"], function($action) use ($form) {
					return $action["form"] == $form;
				});

				foreach ($matching_actions as $action) {
					if ($action["route"] == "edit") {
						$action["module"] = $module["id"];

						return $action;
					}
				}

				if (count($matching_actions)) {
					$matching_actions[0]["module"] = $module["id"];

					return $matching_actions[0];
				}
			}
		}

		public static function getModuleEmbedForms($sort = "title", $module = false) {
			$sort_parts = explode(" ", $sort);
			$sort_column = $sort_parts[0] ?? "";
			$sort_direction = $sort_parts[1] ?? "";

			if ($module) {
				$context = BigTreeJSONDB::getSubset("modules", $module);

				return $context->getAll("embeddable-forms", $sort_column, $sort_direction);
			} else {
				$forms = [];
				$sort_field = [];
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					if (!empty($module["embeddable-forms"])) {
						$forms = array_merge($forms, array_filter((array) $module["embeddable-forms"]));
					}
				}

				foreach ($forms as $form) {
					$sort_field[] = $form[$sort_column];
				}

				if ($sort_direction == "DESC") {
					array_multisort($sort_field, SORT_DESC, $forms);
				} else {
					array_multisort($sort_field, SORT_ASC, $forms);
				}

				return $forms;
			}
		}

	}
