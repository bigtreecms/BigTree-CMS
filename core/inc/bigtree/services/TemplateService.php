<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Flag;
	use BigTree\Api\Resources;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTreeJSONDB;
	use BigTree;

	class TemplateService {
		public function list(Request $request) {
			$mtime_path = SERVER_ROOT . "core/setup/json-db/templates.json";
			$custom_mtime_path = SERVER_ROOT . "custom/setup/json-db/templates.json";
			$etag = ETag::fromMtimes(array_filter([$mtime_path, file_exists($custom_mtime_path) ? $custom_mtime_path : null]));

			if (ETag::check($request, $etag)) {
				return Response::notModified($etag);
			}

			$rows = BigTreeJSONDB::getAll("templates", "position", "DESC");
			$items = array_map([$this, "present"], $rows);

			$r = Response::ok($items);
			$r->header("ETag", $etag);
			$r->header("Cache-Control", "private, max-age=60");

			return $r;
		}

		public function get(Request $request) {
			$id = $request->routeParam("id");
			$t = Entity::findOrFailJson("templates", $id, "Template");

			return Response::ok($this->present($t));
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (string)$d["id"];

			if (!Sanitize::isValidId($id)) {
				throw new BadRequestException("Template id must be alphanumeric (with - or _) and ≤ 127 chars", "invalid_id");
			}

			(new JsonStore("templates", "Template"))->assertAbsent($id);

			$resources = Resources::clean($d["resources"] ?? []);
			BigTreeJSONDB::incrementPosition("templates");
			BigTreeJSONDB::insert("templates", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"module" => $d["module"] ?? "",
				"resources" => $resources,
				"level" => (int)($d["level"] ?? 0),
				"routed" => Flag::checkbox($d["routed"] ?? null),
				"position" => 0,
				"hooks" => is_array($d["hooks"] ?? null) ? $d["hooks"] : [],
			]);

			return Response::created($this->present(BigTreeJSONDB::get("templates", $id)), null);
		}

		public function update(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("templates", $id, "Template");

			$d = $request->body;
			$next = array_merge($existing, [
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : $existing["name"],
				"module" => $d["module"] ?? ($existing["module"] ?? ""),
				"level" => isset($d["level"]) ? (int)$d["level"] : (int)($existing["level"] ?? 0),
				"resources" => isset($d["resources"]) ? Resources::clean($d["resources"]) : ($existing["resources"] ?? []),
				"hooks" => isset($d["hooks"]) && is_array($d["hooks"]) ? $d["hooks"] : ($existing["hooks"] ?? []),
			]);
			BigTreeJSONDB::update("templates", $id, $next);

			return Response::ok($this->present(BigTreeJSONDB::get("templates", $id)));
		}

		public function delete(Request $request) {
			$id = $request->routeParam("id");
			(new JsonStore("templates", "Template"))->deleteOrFail($id);

			return Response::noContent();
		}

		public function reorder(Request $request) {
			(new JsonStore("templates", "Template"))->reorder($request->bodyArray("ids"));

			return Response::noContent();
		}

		// — helpers —

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
