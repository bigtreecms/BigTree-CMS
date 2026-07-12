<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\FieldSpec;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTreeJSONDB;

	class CalloutService {
		// Column => transform verb (see FieldSpec). Create/update both use this map.
		private const FIELDS = [
			"name" => "encode",
			"description" => "encode",
			"level" => "int",
			"resources" => "clean",
			"display_field" => "string",
			"display_default" => "string",
		];

		private const GROUP_FIELDS = [
			"name" => "encode",
			"callouts" => "array",
		];

		public function list(Request $request) {
			$rows = BigTreeJSONDB::getAll("callouts", "position", "DESC");

			return Response::ok(array_map([$this, "present"], $rows));
		}

		public function get(Request $request) {
			$id = $request->routeParam("id");
			$c = Entity::findOrFailJson("callouts", $id, "Callout");

			return Response::ok($this->present($c));
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (new JsonStore("callouts", "Callout"))->requireNewId($d);

			// name defaults to the id when the caller omits it.
			if (!isset($d["name"])) {
				$d["name"] = $id;
			}

			$insert = FieldSpec::insert($d, self::FIELDS);
			$insert["id"] = $id;
			$insert["position"] = 0;

			BigTreeJSONDB::incrementPosition("callouts");
			BigTreeJSONDB::insert("callouts", $insert);

			return Response::created($this->present(BigTreeJSONDB::get("callouts", $id)), null);
		}

		public function update(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("callouts", $id, "Callout");

			$d = $request->body;
			$next = array_merge($existing, FieldSpec::update($d, self::FIELDS));
			BigTreeJSONDB::update("callouts", $id, $next);

			return Response::ok($this->present(BigTreeJSONDB::get("callouts", $id)));
		}

		public function delete(Request $request) {
			$id = $request->routeParam("id");
			(new JsonStore("callouts", "Callout"))->deleteOrFail($id);

			return Response::noContent();
		}

		public function reorder(Request $request) {
			(new JsonStore("callouts", "Callout"))->reorder($request->bodyArray("ids"));

			return Response::noContent();
		}

		// — Groups —

		public function listGroups(Request $request) {

			return Response::ok(BigTreeJSONDB::getAll("callout-groups", "name"));
		}

		public function getGroup(Request $request) {
			$id = $request->routeParam("id");
			$g = Entity::findOrFailJson("callout-groups", $id, "Callout group");

			return Response::ok($g);
		}

		public function createGroup(Request $request) {
			$d = $request->body;
			// requireNewId adds the isValidId guard that create siblings already had.
			$id = (new JsonStore("callout-groups", "Callout group"))->requireNewId($d);

			if (!isset($d["name"])) {
				$d["name"] = $id;
			}

			$insert = FieldSpec::insert($d, self::GROUP_FIELDS);
			$insert["id"] = $id;
			BigTreeJSONDB::insert("callout-groups", $insert);

			return Response::created(BigTreeJSONDB::get("callout-groups", $id), null);
		}

		public function updateGroup(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("callout-groups", $id, "Callout group");
			$d = $request->body;
			$next = array_merge($existing, FieldSpec::update($d, self::GROUP_FIELDS));
			BigTreeJSONDB::update("callout-groups", $id, $next);

			return Response::ok(BigTreeJSONDB::get("callout-groups", $id));
		}

		public function deleteGroup(Request $request) {
			$id = $request->routeParam("id");
			(new JsonStore("callout-groups", "Callout group"))->deleteOrFail($id);

			return Response::noContent();
		}

		// — helpers —

		private function present(array $c) {

			return [
				"id" => $c["id"],
				"name" => $c["name"] ?? $c["id"],
				"description" => $c["description"] ?? "",
				"level" => (int)($c["level"] ?? 0),
				"position" => (int)($c["position"] ?? 0),
				"display_field" => $c["display_field"] ?? "",
				"display_default" => $c["display_default"] ?? "",
				"resources" => $c["resources"] ?? [],
			];
		}
	}
