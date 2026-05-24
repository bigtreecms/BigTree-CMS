<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree;
	use BigTreeAdmin;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * Extension management. v1 supports list / get / delete. Install via zip
	 * upload is deferred — the legacy installer at admin.php:6541 has 100+ lines
	 * of file/dependency handling that's safer to migrate later.
	 */
	class ExtensionService {
		public function list(Request $request) {
			$sort = (string)($request->query["sort"] ?? "name");
			$dir = $sort[0] === "-" ? "DESC" : "ASC";
			$col = ltrim($sort, "-");
			$rows = BigTreeJSONDB::getAll("extensions", $col, $dir);
			return Response::ok(array_map([$this, "present"], $rows));
		}

		public function get(Request $request) {
			$id = (string)$request->route_params["id"];
			$ext = BigTreeAdmin::getExtension($id);
			if (!$ext) throw new NotFoundException("Extension $id not found", "resource_not_found", 404);
			return Response::ok($this->present($ext));
		}

		public function delete(Request $request) {
			$id = (string)$request->route_params["id"];
			$ext = BigTreeAdmin::getExtension($id);
			if (!$ext) throw new NotFoundException("Extension $id not found", "resource_not_found", 404);

			$manifest = $ext["manifest"] ?? [];
			$ext_id = $manifest["id"] ?? "";

			if ($ext_id !== "") {
				BigTree::deleteDirectory(SITE_ROOT . "extensions/$ext_id/");
				BigTree::deleteDirectory(SERVER_ROOT . "extensions/$ext_id/");
			}

			foreach ($manifest["components"] ?? [] as $type => $list) {
				if ($type === "tables") {
					SQL::query("SET SESSION foreign_key_checks = 0");
					foreach ((array)$list as $table => $_) {
						SQL::query("DROP TABLE IF EXISTS `" . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . "`");
					}
					SQL::query("SET SESSION foreign_key_checks = 1");
				} else {
					foreach ((array)$list as $item) {
						if (isset($item["id"])) BigTreeJSONDB::delete(str_replace("_", "-", $type), $item["id"]);
					}
				}
			}

			BigTreeJSONDB::delete("extensions", $id);
			return Response::noContent();
		}

		private function present(array $ext) {
			return [
				"id" => $ext["id"] ?? "",
				"name" => $ext["name"] ?? ($ext["id"] ?? ""),
				"version" => $ext["version"] ?? "",
				"manifest" => $ext["manifest"] ?? [],
				"installed_at" => $ext["installed_at"] ?? null,
			];
		}
	}
