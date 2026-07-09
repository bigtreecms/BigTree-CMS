<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTreeAdmin;
	use BigTree;

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
			$view_id = $request->routeParam("sid");
			[$module_id, $module, $context] = $this->moduleContext($request);
			$existing = $this->requireSub($module, "views", $view_id, "View");

			$d = $request->body;

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

			return $this->deleteSubCascade($request, "views", "view", "View");
		}
	}
