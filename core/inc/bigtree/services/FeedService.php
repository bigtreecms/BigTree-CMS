<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeJSONDB;
	use BigTree;

	class FeedService {
		public function list(Request $request) {

			return Response::ok(BigTreeJSONDB::getAll("feeds", "name"));
		}

		public function get(Request $request) {
			$id = (string)$request->route_params["id"];
			$f = BigTreeJSONDB::get("feeds", $id);

			if (!$f) {
				throw new NotFoundException("Feed $id not found", "resource_not_found", 404);
			}
			return Response::ok($f);
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (string)($d["id"] ?? "");

			if ($id === "" || !ctype_alnum(str_replace(["-", "_"], "", $id))) {
				throw new BadRequestException("id must be alphanumeric (with - or _)", "invalid_id", 400);
			}

			if (BigTreeJSONDB::exists("feeds", $id)) {
				throw new ConflictException("Feed $id already exists", "duplicate_id", 409);
			}

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
			$id = (string)$request->route_params["id"];
			$existing = BigTreeJSONDB::get("feeds", $id);

			if (!$existing) {
				throw new NotFoundException("Feed $id not found", "resource_not_found", 404);
			}
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
			$id = (string)$request->route_params["id"];

			if (!BigTreeJSONDB::exists("feeds", $id)) {
				throw new NotFoundException("Feed $id not found", "resource_not_found", 404);
			}

			BigTreeJSONDB::delete("feeds", $id);

			return Response::noContent();
		}
	}
