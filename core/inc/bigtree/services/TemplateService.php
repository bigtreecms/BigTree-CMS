<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeJSONDB;
	use BigTree;

	class TemplateService {
		public function list(Request $request) {
			$mtime_path = SERVER_ROOT . "core/setup/json-db/templates.json";
			$custom_mtime_path = SERVER_ROOT . "custom/setup/json-db/templates.json";
			$etag = ETag::fromMtimes(array_filter([$mtime_path, file_exists($custom_mtime_path) ? $custom_mtime_path : null]));

			if (ETag::check($request, $etag)) {
				$r = Response::raw(304, []);
				$r->is_envelope = false; $r->body = null;
				$r->header("ETag", $etag);

				return $r;
			}

			$rows = BigTreeJSONDB::getAll("templates", "position", "DESC");
			$items = array_map([$this, "present"], $rows);

			$r = Response::ok($items);
			$r->header("ETag", $etag);
			$r->header("Cache-Control", "private, max-age=60");

			return $r;
		}

		public function get(Request $request) {
			$id = (string)$request->route_params["id"];
			$t = BigTreeJSONDB::get("templates", $id);

			if (!$t) {
				throw new NotFoundException("Template $id not found", "resource_not_found", 404);
			}
			return Response::ok($this->present($t));
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (string)$d["id"];

			if (!ctype_alnum(str_replace(["-", "_"], "", $id)) || strlen($id) > 127) {
				throw new BadRequestException("Template id must be alphanumeric (with - or _) and ≤ 127 chars", "invalid_id", 400);
			}

			if (BigTreeJSONDB::exists("templates", $id)) {
				throw new ConflictException("Template $id already exists", "duplicate_id", 409);
			}

			$resources = $this->cleanResources($d["resources"] ?? []);
			BigTreeJSONDB::incrementPosition("templates");
			BigTreeJSONDB::insert("templates", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"module" => $d["module"] ?? "",
				"resources" => $resources,
				"level" => (int)($d["level"] ?? 0),
				"routed" => !empty($d["routed"]) ? "on" : "",
				"position" => 0,
				"hooks" => is_array($d["hooks"] ?? null) ? $d["hooks"] : [],
			]);

			return Response::created($this->present(BigTreeJSONDB::get("templates", $id)), null);
		}

		public function update(Request $request) {
			$id = (string)$request->route_params["id"];
			$existing = BigTreeJSONDB::get("templates", $id);

			if (!$existing) {
				throw new NotFoundException("Template $id not found", "resource_not_found", 404);
			}

			$d = $request->body;
			$next = array_merge($existing, [
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : $existing["name"],
				"module" => $d["module"] ?? ($existing["module"] ?? ""),
				"level" => isset($d["level"]) ? (int)$d["level"] : (int)($existing["level"] ?? 0),
				"resources" => isset($d["resources"]) ? $this->cleanResources($d["resources"]) : ($existing["resources"] ?? []),
				"hooks" => isset($d["hooks"]) && is_array($d["hooks"]) ? $d["hooks"] : ($existing["hooks"] ?? []),
			]);
			BigTreeJSONDB::update("templates", $id, $next);

			return Response::ok($this->present(BigTreeJSONDB::get("templates", $id)));
		}

		public function delete(Request $request) {
			$id = (string)$request->route_params["id"];

			if (!BigTreeJSONDB::exists("templates", $id)) {
				throw new NotFoundException("Template $id not found", "resource_not_found", 404);
			}

			BigTreeJSONDB::delete("templates", $id);

			return Response::noContent();
		}

		public function reorder(Request $request) {
			$ids = (array)$request->body["ids"];
			$position = count($ids);

			foreach ($ids as $id) {
				if (BigTreeJSONDB::exists("templates", $id)) {
					BigTreeJSONDB::update("templates", $id, ["position" => $position--]);
				}
			}

			return Response::noContent();
		}

		// — helpers —

		private function cleanResources($resources) {
			$out = [];

			foreach ((array)$resources as $r) {
				if (empty($r["id"])) {
					continue;
				}
				$settings = $r["settings"] ?? ($r["options"] ?? []);

				if (is_string($settings)) {
					$settings = json_decode($settings, true) ?: [];
				}
				$out[] = [
					"id" => BigTree::safeEncode($r["id"]),
					"type" => BigTree::safeEncode($r["type"] ?? "text"),
					"title" => BigTree::safeEncode($r["title"] ?? ""),
					"subtitle" => BigTree::safeEncode($r["subtitle"] ?? ""),
					"settings" => BigTree::arrayFilterRecursive($settings ?: []),
				];
			}

			return $out;
		}

		private function present(array $t) {

			return [
				"id" => $t["id"],
				"name" => $t["name"] ?? $t["id"],
				"module" => $t["module"] ?? "",
				"level" => (int)($t["level"] ?? 0),
				"routed" => !empty($t["routed"]),
				"position" => (int)($t["position"] ?? 0),
				"resources" => $t["resources"] ?? [],
				"hooks" => $t["hooks"] ?? [],
			];
		}
	}
