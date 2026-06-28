<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTreeJSONDB;
	use BigTree;

	class FeedService {
		public function list(Request $request) {

			return Response::ok(BigTreeJSONDB::getAll("feeds", "name"));
		}

		public function get(Request $request) {
			$id = $request->routeParam("id");
			$f = Entity::findOrFailJson("feeds", $id, "Feed");

			return Response::ok($f);
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (string)($d["id"] ?? "");

			if (!Sanitize::isValidId($id)) {
				throw new BadRequestException("id must be alphanumeric (with - or _)", "invalid_id");
			}

			(new JsonStore("feeds", "Feed"))->assertAbsent($id);

			BigTreeJSONDB::insert("feeds", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"description" => BigTree::safeEncode($d["description"] ?? ""),
				"table" => $d["table"] ?? "",
				"type" => $d["type"] ?? "",
				"settings" => is_array($d["settings"] ?? null) ? $d["settings"] : [],
				"fields" => is_array($d["fields"] ?? null) ? $d["fields"] : [],
			]);

			return Response::created(BigTreeJSONDB::get("feeds", $id), null);
		}

		public function update(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("feeds", $id, "Feed");
			$d = $request->body;
			$next = array_merge($existing, array_filter([
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : null,
				"description" => isset($d["description"]) ? BigTree::safeEncode($d["description"]) : null,
				"table" => $d["table"] ?? null,
				"type" => $d["type"] ?? null,
				"settings" => isset($d["settings"]) && is_array($d["settings"]) ? $d["settings"] : null,
				"fields" => isset($d["fields"]) && is_array($d["fields"]) ? $d["fields"] : null,
			], function ($v) { return $v !== null; }));
			BigTreeJSONDB::update("feeds", $id, $next);

			return Response::ok(BigTreeJSONDB::get("feeds", $id));
		}

		public function delete(Request $request) {
			$id = $request->routeParam("id");
			(new JsonStore("feeds", "Feed"))->deleteOrFail($id);

			return Response::noContent();
		}
	}
