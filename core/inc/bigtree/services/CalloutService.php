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

	class CalloutService {
		public function list(Request $request) {
			$rows = BigTreeJSONDB::getAll("callouts", "position", "DESC");

			return Response::ok(array_map([$this, "present"], $rows));
		}

		public function get(Request $request) {
			$id = (string)$request->route_params["id"];
			$c = BigTreeJSONDB::get("callouts", $id);

			if (!$c) {
				throw new NotFoundException("Callout $id not found", "resource_not_found", 404);
			}
			return Response::ok($this->present($c));
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (string)$d["id"];

			if (!ctype_alnum(str_replace(["-", "_"], "", $id)) || strlen($id) > 127) {
				throw new BadRequestException("Callout id must be alphanumeric (with - or _) and ≤ 127 chars", "invalid_id", 400);
			}

			if (BigTreeJSONDB::exists("callouts", $id)) {
				throw new ConflictException("Callout $id already exists", "duplicate_id", 409);
			}

			BigTreeJSONDB::incrementPosition("callouts");
			BigTreeJSONDB::insert("callouts", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"description" => BigTree::safeEncode($d["description"] ?? ""),
				"level" => (int)($d["level"] ?? 0),
				"resources" => $this->cleanResources($d["resources"] ?? []),
				"display_field" => $d["display_field"] ?? "",
				"display_default" => $d["display_default"] ?? "",
				"position" => 0,
			]);

			return Response::created($this->present(BigTreeJSONDB::get("callouts", $id)), null);
		}

		public function update(Request $request) {
			$id = (string)$request->route_params["id"];
			$existing = BigTreeJSONDB::get("callouts", $id);

			if (!$existing) {
				throw new NotFoundException("Callout $id not found", "resource_not_found", 404);
			}

			$d = $request->body;
			$next = array_merge($existing, [
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : $existing["name"],
				"description" => isset($d["description"]) ? BigTree::safeEncode($d["description"]) : ($existing["description"] ?? ""),
				"level" => isset($d["level"]) ? (int)$d["level"] : (int)($existing["level"] ?? 0),
				"resources" => isset($d["resources"]) ? $this->cleanResources($d["resources"]) : ($existing["resources"] ?? []),
				"display_field" => $d["display_field"] ?? ($existing["display_field"] ?? ""),
				"display_default" => $d["display_default"] ?? ($existing["display_default"] ?? ""),
			]);
			BigTreeJSONDB::update("callouts", $id, $next);

			return Response::ok($this->present(BigTreeJSONDB::get("callouts", $id)));
		}

		public function delete(Request $request) {
			$id = (string)$request->route_params["id"];

			if (!BigTreeJSONDB::exists("callouts", $id)) {
				throw new NotFoundException("Callout $id not found", "resource_not_found", 404);
			}

			BigTreeJSONDB::delete("callouts", $id);

			return Response::noContent();
		}

		public function reorder(Request $request) {
			$ids = (array)$request->body["ids"];
			$position = count($ids);

			foreach ($ids as $id) {
				if (BigTreeJSONDB::exists("callouts", $id)) {
					BigTreeJSONDB::update("callouts", $id, ["position" => $position--]);
				}
			}

			return Response::noContent();
		}

		// — Groups —

		public function listGroups(Request $request) {

			return Response::ok(BigTreeJSONDB::getAll("callout-groups", "name"));
		}

		public function getGroup(Request $request) {
			$id = (string)$request->route_params["id"];
			$g = BigTreeJSONDB::get("callout-groups", $id);

			if (!$g) {
				throw new NotFoundException("Callout group $id not found", "resource_not_found", 404);
			}
			return Response::ok($g);
		}

		public function createGroup(Request $request) {
			$d = $request->body;
			$id = (string)$d["id"];

			if (BigTreeJSONDB::exists("callout-groups", $id)) {
				throw new ConflictException("Callout group $id already exists", "duplicate_id", 409);
			}

			BigTreeJSONDB::insert("callout-groups", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"callouts" => array_values((array)($d["callouts"] ?? [])),
			]);

			return Response::created(BigTreeJSONDB::get("callout-groups", $id), null);
		}

		public function updateGroup(Request $request) {
			$id = (string)$request->route_params["id"];
			$existing = BigTreeJSONDB::get("callout-groups", $id);

			if (!$existing) {
				throw new NotFoundException("Callout group $id not found", "resource_not_found", 404);
			}
			$d = $request->body;
			$next = array_merge($existing, [
				"name" => isset($d["name"]) ? BigTree::safeEncode($d["name"]) : $existing["name"],
				"callouts" => isset($d["callouts"]) ? array_values((array)$d["callouts"]) : ($existing["callouts"] ?? []),
			]);
			BigTreeJSONDB::update("callout-groups", $id, $next);

			return Response::ok(BigTreeJSONDB::get("callout-groups", $id));
		}

		public function deleteGroup(Request $request) {
			$id = (string)$request->route_params["id"];

			if (!BigTreeJSONDB::exists("callout-groups", $id)) {
				throw new NotFoundException("Callout group $id not found", "resource_not_found", 404);
			}

			BigTreeJSONDB::delete("callout-groups", $id);

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
