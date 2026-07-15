<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\FieldSpec;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\ETag;
	use BigTree\Api\Flag;
	use BigTree\Api\Resources;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\Tools\TemplateToolBackend;
	use BigTree;
	use BigTreeCMS;
	use BigTreeJSONDB;

	class TemplateService implements TemplateToolBackend {
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
	
		public static function getTemplates($sort = "position") {
			$sort_parts = explode(" ", $sort);
			$sort_column = $sort_parts[0] ?? "";
			$sort_direction = $sort_parts[1] ?? "";

			return BigTreeJSONDB::getAll("templates", $sort_column, $sort_direction ?: "ASC");
		}

		// — AI tool seam (TemplateToolBackend) —
		//
		// Reads wrap the same JSONDB the REST routes read; writes reuse the same
		// FieldSpec + Resources cleaning as create()/update() so a template authored by
		// the assistant is shaped identically to one built in the developer UI. Writes
		// re-check developer level (never trusting the model or the stored payload).

		/**
		 * @return list<array<string,mixed>>
		 */
		public function aiListTemplates(): array {
			$rows = BigTreeJSONDB::getAll("templates", "position", "DESC");

			return array_map(function (array $t): array {

				return [
					"id" => (string)$t["id"],
					"name" => (string)($t["name"] ?? $t["id"]),
					"module" => (string)($t["module"] ?? ""),
					"level" => (int)($t["level"] ?? 0),
					"routed" => !empty($t["routed"]),
					"field_count" => is_array($t["resources"] ?? null) ? count($t["resources"]) : 0,
				];
			}, $rows);
		}

		/**
		 * @return array<string,mixed>
		 */
		public function aiGetTemplate(string $id): array {
			$t = BigTreeJSONDB::get("templates", $id);

			if (!$t) {

				return ["error" => "Template \"{$id}\" does not exist."];
			}

			return ["template" => $this->present($t)];
		}

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateTemplateCreate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can create templates."];
			}

			$id = trim((string)($args["id"] ?? ""));

			if ($id === "") {

				return ["error" => "A template id is required (a short lowercase identifier, e.g. \"landing-page\")."];
			}

			$id = BigTreeCMS::urlify($id);

			if (BigTreeJSONDB::exists("templates", $id)) {

				return ["error" => "A template with the id \"{$id}\" already exists."];
			}

			$name = trim((string)($args["name"] ?? "")) ?: $id;
			$level = (int)($args["level"] ?? 0);
			$routed = !empty($args["routed"]);
			$fields = $this->aiCleanResourceFields($args["fields"] ?? []);

			$payload = [
				"id" => $id,
				"name" => $name,
				"level" => $level,
				"routed" => $routed,
				"resources" => $fields,
			];

			return [
				"ok" => true,
				"summary" => "Create a new page template “{$name}” (id {$id}) with " . count($fields) . " field(s).",
				"preview" => [
					"action" => "create_template",
					"id" => $id,
					"name" => $name,
					"level" => $level,
					"routed" => $routed,
					"fields" => $this->aiPreviewFields($fields),
				],
				"payload" => $payload,
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreateTemplate(array $payload, $user): array {
			if (PermissionService::level($user) < 2) {
				throw new AuthorizationException("Only developers can create templates.");
			}

			$id = (string)($payload["id"] ?? "");

			if ($id === "" || BigTreeJSONDB::exists("templates", $id)) {

				return ["mode" => "error", "message" => "That template id is no longer available."];
			}

			$insert = [
				"id" => $id,
				"name" => BigTree::safeEncode((string)($payload["name"] ?? $id)),
				"module" => "",
				"resources" => Resources::clean(is_array($payload["resources"] ?? null) ? $payload["resources"] : []),
				"level" => (int)($payload["level"] ?? 0),
				"routed" => Flag::checkbox(!empty($payload["routed"])),
				"hooks" => [],
				"position" => 0,
			];

			BigTreeJSONDB::incrementPosition("templates");
			BigTreeJSONDB::insert("templates", $insert);

			return [
				"mode" => "created",
				"id" => $id,
				"name" => (string)($payload["name"] ?? $id),
			];
		}

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateTemplateUpdate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can edit templates."];
			}

			$id = trim((string)($args["id"] ?? ""));
			$existing = $id !== "" ? BigTreeJSONDB::get("templates", $id) : null;

			if (!$existing) {

				return ["error" => "Template \"{$id}\" does not exist."];
			}

			$changes = [];
			$diff = [];

			if (array_key_exists("name", $args)) {
				$name = trim((string)$args["name"]);

				if ($name !== "" && $name !== (string)($existing["name"] ?? "")) {
					$changes["name"] = $name;
					$diff["name"] = ["from" => (string)($existing["name"] ?? ""), "to" => $name];
				}
			}

			if (array_key_exists("level", $args)) {
				$level = (int)$args["level"];

				if ($level !== (int)($existing["level"] ?? 0)) {
					$changes["level"] = $level;
					$diff["level"] = ["from" => (int)($existing["level"] ?? 0), "to" => $level];
				}
			}

			if (array_key_exists("fields", $args)) {
				$fields = $this->aiCleanResourceFields($args["fields"]);
				$changes["resources"] = $fields;
				$diff["fields"] = [
					"from" => count(is_array($existing["resources"] ?? null) ? $existing["resources"] : []),
					"to" => count($fields),
				];
			}

			if (!$changes) {

				return ["error" => "No changes were supplied — nothing to update."];
			}

			$name = (string)($existing["name"] ?? $id);

			return [
				"ok" => true,
				"summary" => "Update page template “{$name}” (id {$id}).",
				"preview" => [
					"action" => "update_template",
					"id" => $id,
					"name" => $name,
					"changes" => $diff,
				],
				"payload" => [
					"id" => $id,
					"changes" => $changes,
				],
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdateTemplate(array $payload, $user): array {
			if (PermissionService::level($user) < 2) {
				throw new AuthorizationException("Only developers can edit templates.");
			}

			$id = (string)($payload["id"] ?? "");
			$existing = $id !== "" ? BigTreeJSONDB::get("templates", $id) : null;

			if (!$existing) {

				return ["mode" => "error", "message" => "Template no longer exists."];
			}

			$changes = is_array($payload["changes"] ?? null) ? $payload["changes"] : [];
			$next = $existing;

			if (array_key_exists("name", $changes)) {
				$next["name"] = BigTree::safeEncode((string)$changes["name"]);
			}

			if (array_key_exists("level", $changes)) {
				$next["level"] = (int)$changes["level"];
			}

			if (array_key_exists("resources", $changes)) {
				$next["resources"] = Resources::clean(is_array($changes["resources"]) ? $changes["resources"] : []);
			}

			BigTreeJSONDB::update("templates", $id, $next);

			return [
				"mode" => "updated",
				"id" => $id,
				"name" => (string)($next["name"] ?? $id),
			];
		}

		/**
		 * Normalize the model's proposed template fields into the canonical resource
		 * shape (id, type, title, subtitle) before cleaning. Fields without an id are
		 * dropped by Resources::clean.
		 *
		 * @param mixed $fields
		 * @return list<array<string,mixed>>
		 */
		private function aiCleanResourceFields($fields): array {
			$rows = [];

			foreach ((array)$fields as $field) {
				if (!is_array($field)) {

					continue;
				}

				$rows[] = [
					"id" => BigTreeCMS::urlify((string)($field["id"] ?? "")),
					"type" => (string)($field["type"] ?? "text"),
					"title" => (string)($field["title"] ?? ""),
					"subtitle" => (string)($field["subtitle"] ?? ""),
				];
			}

			return Resources::clean($rows);
		}

		/**
		 * Compact field list for a proposal preview.
		 *
		 * @param list<array<string,mixed>> $fields
		 * @return list<array<string,mixed>>
		 */
		private function aiPreviewFields(array $fields): array {

			return array_map(function (array $f): array {

				return [
					"id" => (string)($f["id"] ?? ""),
					"type" => (string)($f["type"] ?? ""),
					"title" => (string)($f["title"] ?? ""),
				];
			}, $fields);
		}

	}
