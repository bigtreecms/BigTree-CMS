<?php
	namespace BigTree\Services;

	use BigTree\Api\Flag;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeAdmin;
	use BigTreeJSONDB;
	use BigTree;
	use SQL;

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
				"tagging" => Flag::checkbox($d["tagging"] ?? null),
				"open_graph" => Flag::checkbox($d["open_graph"] ?? null),
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
				throw new NotFoundException("Form $form_id not found");
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
				$update["tagging"] = Flag::checkbox($d["tagging"]);
			}

			if (array_key_exists("open_graph", $d)) {
				$update["open_graph"] = Flag::checkbox($d["open_graph"]);
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
			$module_id = $request->route_params["id"];
			$form_id = $request->route_params["sid"];
			$module = $this->loadModule($module_id);
			$existing = $this->findSub($module["forms"] ?? [], $form_id);

			if (!$existing) {
				throw new NotFoundException("Form $form_id not found");
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

		public function relationOptions(Request $request) {
			$module_id = $request->route_params["id"];
			$form_id = $request->route_params["sid"];
			$column = $request->queryString("column", "", false);

			if ($column === "") {
				throw new BadRequestException("`column` query param is required", "missing_column");
			}

			$module = $this->loadModule($module_id);
			$form = $this->findSub($module["forms"] ?? [], $form_id);

			if (!$form) {
				throw new NotFoundException("Form $form_id not found");
			}

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

			if ($table === "" || $descriptor === "") {
				throw new BadRequestException("Field `$column` is missing table/descriptor settings", "invalid_field_settings");
			}

			$schema = BigTree::describeTable($table);

			if (!$schema || empty($schema["columns"])) {
				throw new NotFoundException("Table `$table` not found");
			}

			if (empty($schema["columns"][$descriptor])) {
				throw new BadRequestException("Descriptor column `$descriptor` does not exist on `$table`", "invalid_field_settings");
			}

			// Sort clause — validate against the column list so we never
			// concatenate user-controlled text into the SQL. Default to the
			// descriptor when the configured sort references a missing column.
			$order_by = \BigTree\Api\Sanitize::orderClause($sort, $schema["columns"], $descriptor);

			$entry_raw = $request->queryString("entry");

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

			$q = $request->queryString("q");

			if ($q !== "") {
				$where[] = "`$descriptor` LIKE ?";
				$params[] = "%" . $q . "%";
			}

			$ids_raw = $request->queryString("ids", "", false);

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
			$column = $request->queryString("column", "", false);

			if ($column === "") {
				throw new BadRequestException("`column` query param is required", "missing_column");
			}

			$module = $this->loadModule($module_id);
			$form = $this->findSub($module["forms"] ?? [], $form_id);

			if (!$form) {
				throw new NotFoundException("Form $form_id not found");
			}

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

			if ((string)($field["type"] ?? "") !== "list") {
				throw new BadRequestException("Field `$column` is not a list field", "invalid_field_type");
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
				throw new BadRequestException("Field `$column` is missing table/descriptor settings", "invalid_field_settings");
			}

			$schema = BigTree::describeTable($table);

			if (!$schema || empty($schema["columns"])) {
				throw new NotFoundException("Table `$table` not found");
			}

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
	}
