<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree;
	use BigTreeJSONDB;
	use BigTreeAutoModule;

	/**
	 * Module views: CRUD over the view sub-resources stored in a module's JSONDB
	 * record, plus the gbp-categories lookup that drives the user editor's
	 * group-based-permission radios.
	 *
	 * Extracted from ModuleService (god-object decomposition, plan 028 — the View
	 * cluster, following 027's Report cluster). Behavior is identical to the
	 * former ModuleService methods; this is a pure relocation. Sub-resource
	 * resolution (loadModule/findSub/getSubResource) is shared with ModuleService
	 * via ModuleSubResourceSupport. The Kernel instantiates this service per
	 * request, so the view routes dispatch to it exactly as they did to
	 * ModuleService.
	 */
	class ModuleViewService {
		use ModuleSubResourceSupport;

		// The view sub-resource write shape: column => transform verb (see
		// ModuleSubResourceSupport::buildInsert). Views have no per-entity
		// specials, so this drives both create and update wholesale.
		private const FIELDS = [
			"title" => "encode",
			"description" => "encode",
			"table" => "string",
			"type" => "string",
			"settings" => "array",
			"fields" => "array",
			"actions" => "array",
			"related_form" => "nullable",
			"preview_url" => "encode",
			"exclude_from_search" => "bool",
		];

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
			$module = $this->loadModule($request->routeParam("id"));
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

		// — View CRUD —

		public function createView(Request $request) {
			[$module_id, , $context] = $this->moduleContext($request);
			$d = $request->body;

			$id = $context->insert("views", $this->buildInsert($d, self::FIELDS));
			$this->syncNumericStatus((string)($d["table"] ?? ""));

			return Response::created($this->getSubResource($module_id, "views", $id), null);
		}

		public function updateView(Request $request) {
			$view_id = $request->routeParam("sid");
			[$module_id, $module, $context] = $this->moduleContext($request);
			$existing = $this->requireSub($module, "views", $view_id, "View");

			$d = $request->body;
			$update = $this->buildUpdate($d, self::FIELDS);

			if ($update) {
				$context->update("views", $view_id, $update);
				$this->syncNumericStatus((string)($update["table"] ?? ($existing["table"] ?? "")));
			}

			return Response::ok($this->getSubResource($module_id, "views", $view_id));
		}

		public function deleteView(Request $request) {

			return $this->deleteSubCascade($request, "views", "view", "View");
		}
	
		public static function getModuleViews($sort = "title", $module = false) {
			$sort_pieces = explode(" ", $sort);
			$sort_column = $sort_pieces[0] ?? "";
			$sort_direction = $sort_pieces[1] ?? "";

			if ($module) {
				$context = BigTreeJSONDB::getSubset("modules", $module);

				return $context->getAll("views", $sort_column, $sort_direction);
			} else {
				$views = [];
				$sort_field = [];
				$modules = BigTreeJSONDB::getAll("modules");

				foreach ($modules as $module) {
					$views = array_merge($views, array_filter((array) $module["views"]));
				}

				foreach ($views as $view) {
					$sort_field[] = $view[$sort_column];
				}

				if ($sort_direction == "DESC") {
					array_multisort($sort_field, SORT_DESC, $views);
				} else {
					array_multisort($sort_field, SORT_ASC, $views);
				}

				return $views;
			}
		}

		public static function updateModuleViewColumnNumericStatusForTable($table_name) {
			$modules = BigTreeJSONDB::getAll("modules");

			foreach ($modules as $module) {
				foreach ($module["views"] as $view) {
					if ($view["table"] == $table_name) {
						if (is_array($view["fields"])) {
							$form = BigTreeAutoModule::getRelatedFormForView($view);
							$table = BigTree::describeTable($view["table"]);

							foreach ($view["fields"] as $key => $field) {
								$numeric = false;
								$type = $table["columns"][$key]["type"];

								if (in_array($type, ["int", "float", "double", "double precision", "tinyint", "smallint", "mediumint", "bigint", "real", "decimal", "dec", "fixed", "numeric"])) {
									$numeric = true;
								}

								if (!empty($field["parser"]) ||
									(!empty($form["fields"][$key]["type"]) && $form["fields"][$key]["type"] == "list" && $form["fields"][$key]["settings"]["list_type"] == "db")
								) {
									$numeric = false;
								}

								$view["fields"][$key]["numeric"] = $numeric;
							}

							$context = BigTreeJSONDB::getSubset("modules", $module["id"]);
							$context->update("views", $view["id"], $view);
						}
					}
				}
			}
		}

	}
