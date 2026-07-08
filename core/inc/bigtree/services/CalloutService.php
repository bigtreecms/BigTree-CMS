<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Resources;
	use BigTree\Api\Sanitize;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTreeJSONDB;
	use BigTree;

	class CalloutService {
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
			$id = (string)$d["id"];

			if (!Sanitize::isValidId($id)) {
				throw new BadRequestException("Callout id must be alphanumeric (with - or _) and ≤ 127 chars", "invalid_id");
			}

			(new JsonStore("callouts", "Callout"))->assertAbsent($id);

			BigTreeJSONDB::incrementPosition("callouts");
			BigTreeJSONDB::insert("callouts", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"description" => BigTree::safeEncode($d["description"] ?? ""),
				"level" => (int)($d["level"] ?? 0),
				"resources" => Resources::clean($d["resources"] ?? []),
				"display_field" => $d["display_field"] ?? "",
				"display_default" => $d["display_default"] ?? "",
				"position" => 0,
			]);

			return Response::created($this->present(BigTreeJSONDB::get("callouts", $id)), null);
		}

		public function update(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("callouts", $id, "Callout");

			$d = $request->body;
			$next = array_merge($existing, [
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : $existing["name"],
				"description" => isset($d["description"]) ? BigTree::safeEncode($d["description"]) : ($existing["description"] ?? ""),
				"level" => isset($d["level"]) ? (int)$d["level"] : (int)($existing["level"] ?? 0),
				"resources" => isset($d["resources"]) ? Resources::clean($d["resources"]) : ($existing["resources"] ?? []),
				"display_field" => $d["display_field"] ?? ($existing["display_field"] ?? ""),
				"display_default" => $d["display_default"] ?? ($existing["display_default"] ?? ""),
			]);
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
			$id = (string)$d["id"];

			(new JsonStore("callout-groups", "Callout group"))->assertAbsent($id);

			BigTreeJSONDB::insert("callout-groups", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"callouts" => array_values((array)($d["callouts"] ?? [])),
			]);

			return Response::created(BigTreeJSONDB::get("callout-groups", $id), null);
		}

		public function updateGroup(Request $request) {
			$id = $request->routeParam("id");
			$existing = Entity::findOrFailJson("callout-groups", $id, "Callout group");
			$d = $request->body;
			$next = array_merge($existing, [
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : $existing["name"],
				"callouts" => isset($d["callouts"]) ? array_values((array)$d["callouts"]) : ($existing["callouts"] ?? []),
			]);
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
