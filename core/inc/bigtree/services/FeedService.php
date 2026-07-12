<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\FieldSpec;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTreeJSONDB;

	class FeedService {
		// Column => transform verb (see FieldSpec).
		private const FIELDS = [
			"name" => "encode",
			"description" => "encode",
			"table" => "string",
			"type" => "string",
			"settings" => "array",
			"fields" => "array",
		];

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
			$id = (new JsonStore("feeds", "Feed"))->requireNewId($d);

			if (!isset($d["name"])) {
				$d["name"] = $id;
			}

			$insert = FieldSpec::insert($d, self::FIELDS);
			$insert["id"] = $id;
			BigTreeJSONDB::insert("feeds", $insert);

			return Response::created(BigTreeJSONDB::get("feeds", $id), null);
		}

		public function update(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("feeds", $id, "Feed");
			$d = $request->body;
			$next = array_merge($existing, FieldSpec::update($d, self::FIELDS));
			BigTreeJSONDB::update("feeds", $id, $next);

			return Response::ok(BigTreeJSONDB::get("feeds", $id));
		}

		public function delete(Request $request) {
			$id = $request->routeParam("id");
			(new JsonStore("feeds", "Feed"))->deleteOrFail($id);

			return Response::noContent();
		}
	}
