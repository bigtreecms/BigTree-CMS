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
				return PermissionService::userHasModuleAccess($me, (int)$m["id"], "v");
			});

			$enriched = array_map(function ($m) {
				$group_name = "";
				if (!empty($m["group"])) {
					$g = BigTreeJSONDB::get("module-groups", $m["group"]);
					if ($g) $group_name = $g["name"];
				}
				return $this->present($m, $group_name);
			}, array_values($visible));

			return Response::ok($enriched);
		}

		public function get(Request $request) {
			$id = (int)$request->route_params["id"];
			$m = BigTreeJSONDB::get("modules", $id);
			if (!$m) throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			if (!PermissionService::userHasModuleAccess($request->user, $id, "v")) {
				throw new \BigTree\Api\Exceptions\AuthorizationException("Module access denied", "permission_denied", 403);
			}
			$group_name = "";
			if (!empty($m["group"])) {
				$g = BigTreeJSONDB::get("module-groups", $m["group"]);
				if ($g) $group_name = $g["name"];
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
			$id = (int)BigTreeJSONDB::insert("modules", [
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
			$id = (int)$request->route_params["id"];
			$existing = BigTreeJSONDB::get("modules", $id);
			if (!$existing) throw new NotFoundException("Module $id not found", "resource_not_found", 404);
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
			$id = (int)$request->route_params["id"];
			if (!BigTreeJSONDB::exists("modules", $id)) {
				throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			}
			BigTreeJSONDB::delete("modules", $id);
			// Cascade: cleanup module-forms / module-views / module-reports / module-actions / module-embed-forms
			foreach (["module-forms", "module-views", "module-reports", "module-actions", "module-embed-forms"] as $sub) {
				$entries = BigTreeJSONDB::getAll($sub);
				foreach ($entries as $e) {
					if ((int)($e["module"] ?? 0) === $id) {
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

		// — sub-resources (read-only in v1) —

		public function actions(Request $request) {
			$id = (int)$request->route_params["id"];
			$rows = BigTreeJSONDB::getAll("module-actions");
			return Response::ok(array_values(array_filter($rows, function ($a) use ($id) { return (int)($a["module"] ?? 0) === $id; })));
		}

		public function forms(Request $request) {
			$id = (int)$request->route_params["id"];
			$rows = BigTreeJSONDB::getAll("module-forms");
			return Response::ok(array_values(array_filter($rows, function ($a) use ($id) { return (int)($a["module"] ?? 0) === $id; })));
		}

		public function views(Request $request) {
			$id = (int)$request->route_params["id"];
			$rows = BigTreeJSONDB::getAll("module-views");
			return Response::ok(array_values(array_filter($rows, function ($a) use ($id) { return (int)($a["module"] ?? 0) === $id; })));
		}

		public function reports(Request $request) {
			$id = (int)$request->route_params["id"];
			$rows = BigTreeJSONDB::getAll("module-reports");
			return Response::ok(array_values(array_filter($rows, function ($a) use ($id) { return (int)($a["module"] ?? 0) === $id; })));
		}

		// — groups —

		public function listGroups(Request $request) {
			return Response::ok(BigTreeJSONDB::getAll("module-groups", "position", "DESC"));
		}

		public function getGroup(Request $request) {
			$id = (int)$request->route_params["id"];
			$g = BigTreeJSONDB::get("module-groups", $id);
			if (!$g) throw new NotFoundException("Module group $id not found", "resource_not_found", 404);
			return Response::ok($g);
		}

		public function createGroup(Request $request) {
			$d = $request->body;
			$id = (int)BigTreeJSONDB::insert("module-groups", [
				"name" => BigTree::safeEncode($d["name"]),
				"route" => $d["route"] ?? BigTreeCMS::urlify($d["name"]),
				"position" => 0,
			]);
			return Response::created(BigTreeJSONDB::get("module-groups", $id), null);
		}

		public function updateGroup(Request $request) {
			$id = (int)$request->route_params["id"];
			$existing = BigTreeJSONDB::get("module-groups", $id);
			if (!$existing) throw new NotFoundException("Module group $id not found", "resource_not_found", 404);
			$d = $request->body;
			BigTreeJSONDB::update("module-groups", $id, array_merge($existing, array_filter([
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : null,
				"route" => $d["route"] ?? null,
			], function ($v) { return $v !== null; })));
			return Response::ok(BigTreeJSONDB::get("module-groups", $id));
		}

		public function deleteGroup(Request $request) {
			$id = (int)$request->route_params["id"];
			if (!BigTreeJSONDB::exists("module-groups", $id)) {
				throw new NotFoundException("Module group $id not found", "resource_not_found", 404);
			}
			BigTreeJSONDB::delete("module-groups", $id);
			// Detach modules from this group
			$modules = BigTreeJSONDB::getAll("modules");
			foreach ($modules as $m) {
				if ((int)($m["group"] ?? 0) === $id) {
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
				"id" => (int)$m["id"],
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
