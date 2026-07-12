<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\FieldSpec;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Flag;
	use BigTreeJSONDB;

	class TemplateService {
		// Column => transform verb (see FieldSpec). `routed` is create-only and
		// stays explicit; update never rewrites it.
		private const FIELDS = [
			"name" => "encode",
			"module" => "string",
			"resources" => "clean",
			"level" => "int",
			"hooks" => "array",
		];

		public function list(Request $request) {
			$mtime_path = SERVER_ROOT . "core/setup/json-db/templates.json";
			$custom_mtime_path = SERVER_ROOT . "custom/setup/json-db/templates.json";
			$etag = ETag::fromMtimes(array_filter([$mtime_path, file_exists($custom_mtime_path) ? $custom_mtime_path : null]));

			if (ETag::check($request, $etag)) {
				return Response::notModified($etag);
			}

			$rows = BigTreeJSONDB::getAll("templates", "position", "DESC");
			$items = array_map([$this, "present"], $rows);

			return Response::ok($items)
				->header("ETag", $etag)
				->cacheFor(60);
		}

		public function get(Request $request) {
			$id = $request->routeParam("id");
			$t = Entity::findOrFailJson("templates", $id, "Template");

			return Response::ok($this->present($t));
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (new JsonStore("templates", "Template"))->requireNewId($d);

			if (!isset($d["name"])) {
				$d["name"] = $id;
			}

			$insert = FieldSpec::insert($d, self::FIELDS);
			$insert["id"] = $id;
			$insert["routed"] = Flag::checkbox($d["routed"] ?? null);
			$insert["position"] = 0;

			BigTreeJSONDB::incrementPosition("templates");
			BigTreeJSONDB::insert("templates", $insert);

			return Response::created($this->present(BigTreeJSONDB::get("templates", $id)), null);
		}

		public function update(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("templates", $id, "Template");

			$d = $request->body;
			$next = array_merge($existing, FieldSpec::update($d, self::FIELDS));
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
