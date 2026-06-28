<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeJSONDB;
	use BigTree;
	use BigTreeAutoModule;

	/**
	 * Module reports: CRUD over the report sub-resources stored in a module's
	 * JSONDB record, plus the prepare/run endpoints that drive the SPA's report
	 * UI off the legacy BigTreeAutoModule report engine.
	 *
	 * Extracted from ModuleService (god-object decomposition, plan 027 phase B).
	 * Behavior is identical to the former ModuleService methods; this is a pure
	 * relocation. Sub-resource resolution (loadModule/findSub/getSubResource) is
	 * shared with ModuleService via ModuleSubResourceSupport. The Kernel
	 * instantiates this service per request, so the report routes dispatch to it
	 * exactly as they did to ModuleService.
	 */
	class ModuleReportService {
		use ModuleSubResourceSupport;

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
				throw new NotFoundException("Report $report_id not found");
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
				throw new NotFoundException("Report $report_id not found");
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
				throw new NotFoundException("Report $report_id not found");
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
				throw new NotFoundException("Report $report_id not found");
			}

			$report = \BigTreeAutoModule::getReport($report_id);

			if (!$report) {
				throw new NotFoundException("Report $report_id not found");
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
	}
