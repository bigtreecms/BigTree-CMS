<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\FieldSpec;
	use BigTree\Api\JsonStore;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Resources;
	use BigTree\Api\TemplateScaffold;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\Tools\CalloutToolBackend;
	use BigTree;
	use BigTreeCMS;
	use BigTreeJSONDB;

	class CalloutService implements CalloutToolBackend {
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

			// Legacy's developer UI scaffolded the render file on create; without it a
			// callout can be placed on pages that then render nothing. A write failure
			// is surfaced on the response but never unwinds the record.
			$scaffolded = "";

			try {
				$scaffolded = TemplateScaffold::callout(
					$id,
					is_array($insert["resources"] ?? null) ? $insert["resources"] : []
				);
			} catch (\Throwable $e) {
				$scaffolded = "";
			}

			$body = $this->present(BigTreeJSONDB::get("callouts", $id));
			$body["scaffolded_file"] = $scaffolded;

			return Response::created($body, null);
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
	
		public static function getCallout($id) {
			return BigTreeJSONDB::get("callouts", $id);
		}

		public static function getCallouts($sort = "position") {
			$sort_pieces = explode(" ", $sort);
			$sort_column = $sort_pieces[0] ?? "";
			$sort_direction = $sort_pieces[1] ?? "";

			return BigTreeJSONDB::getAll("callouts", $sort_column, $sort_direction ?: "ASC");
		}

		// — AI tool seam (CalloutToolBackend) —
		//
		// Developer-only two-phase callout creation, shaped exactly like the REST
		// create path (FieldSpec/Resources cleaning) but packaged without a Request.
		// Developer level is re-checked at validation and approval.

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateCalloutCreate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can create callouts."];
			}

			$id = trim((string)($args["id"] ?? ""));

			if ($id === "") {

				return ["error" => "A callout id is required (a short lowercase identifier)."];
			}

			$id = BigTreeCMS::urlify($id);

			if (BigTreeJSONDB::exists("callouts", $id)) {

				return ["error" => "A callout with the id \"{$id}\" already exists."];
			}

			$name = trim((string)($args["name"] ?? "")) ?: $id;
			$fields = Resources::clean($this->aiCalloutFields($args["fields"] ?? []));
			$type_error = $this->aiInvalidFieldTypeError($fields);

			if ($type_error !== null) {

				return ["error" => $type_error];
			}

			$display_field = $this->aiResolveDisplayField($args["display_field"] ?? "", $fields);

			if (isset($display_field["error"])) {

				return $display_field;
			}

			// Fail here rather than half-way through an approved write.
			if (!TemplateScaffold::calloutIsWritable($id)) {

				return ["error" => "The templates/callouts/ directory is not writable, so the callout's render file "
					. "can't be created. Fix the directory permissions and try again."];
			}

			$stub = "templates/callouts/{$id}.php";
			$payload = [
				"id" => $id,
				"name" => $name,
				"description" => trim((string)($args["description"] ?? "")),
				"level" => (int)($args["level"] ?? 0),
				"display_field" => $display_field["value"],
				"display_default" => trim((string)($args["display_default"] ?? "")),
				"resources" => $fields,
			];

			return [
				"ok" => true,
				"summary" => "Create a new callout “{$name}” (id {$id}) with " . count($fields) . " field(s). "
					. "A starter render file will be created at {$stub}.",
				"preview" => [
					"action" => "create_callout",
					"id" => $id,
					"name" => $name,
					"level" => (int)($args["level"] ?? 0),
					"display_field" => $display_field["value"],
					"creates_file" => $stub,
					"fields" => array_map(function (array $f): array {

						return [
							"id" => (string)($f["id"] ?? ""),
							"type" => (string)($f["type"] ?? ""),
							"title" => (string)($f["title"] ?? ""),
						];
					}, $fields),
				],
				"payload" => $payload,
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreateCallout(array $payload, $user): array {
			if (PermissionService::level($user) < 2) {
				throw new AuthorizationException("Only developers can create callouts.");
			}

			$id = (string)($payload["id"] ?? "");

			if ($id === "" || BigTreeJSONDB::exists("callouts", $id)) {

				return ["mode" => "error", "message" => "That callout id is no longer available."];
			}

			$insert = [
				"id" => $id,
				"name" => BigTree::safeEncode((string)($payload["name"] ?? $id)),
				"description" => BigTree::safeEncode((string)($payload["description"] ?? "")),
				"level" => (int)($payload["level"] ?? 0),
				"resources" => Resources::clean(is_array($payload["resources"] ?? null) ? $payload["resources"] : []),
				"display_field" => (string)($payload["display_field"] ?? ""),
				"display_default" => BigTree::safeEncode((string)($payload["display_default"] ?? "")),
				"position" => 0,
			];

			BigTreeJSONDB::incrementPosition("callouts");
			BigTreeJSONDB::insert("callouts", $insert);

			$scaffolded = "";
			$scaffold_error = "";

			try {
				$scaffolded = TemplateScaffold::callout($id, $insert["resources"]);
			} catch (\Throwable $e) {
				$scaffold_error = $e->getMessage();
			}

			return [
				"mode" => "created",
				"id" => $id,
				"name" => (string)($payload["name"] ?? $id),
				"file" => $scaffolded,
				"note" => $scaffolded !== ""
					? "A starter render file was created at {$scaffolded} — edit it to control how this callout looks."
					: ($scaffold_error !== ""
						? "The callout record was created, but its render file could not be written ({$scaffold_error}). "
							. "Pages using this callout will render nothing until the file exists."
						: "A render file already existed for this callout and was left untouched."),
			];
		}

		/**
		 * Normalize the model's proposed callout fields into the canonical resource
		 * shape before cleaning.
		 *
		 * @param mixed $fields
		 * @return list<array<string,mixed>>
		 */
		private function aiCalloutFields($fields): array {
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

			return $rows;
		}

		/**
		 * Reject any field whose `type` isn't an installed callout field type — a
		 * model-invented type was previously stored verbatim and rendered as a broken
		 * field in the callout editor.
		 *
		 * Returns null when every type is valid, else a recoverable error.
		 *
		 * @param list<array<string,mixed>> $fields
		 */
		private function aiInvalidFieldTypeError(array $fields): ?string {
			$valid = FieldTypeService::availableFieldTypeIds("callouts");

			// An empty catalog means the registry couldn't be resolved — don't turn
			// that into a refusal of every field.
			if (!$valid) {

				return null;
			}

			$bad = [];

			foreach ($fields as $field) {
				$type = (string)($field["type"] ?? "");

				if ($type !== "" && !in_array($type, $valid, true)) {
					$bad[] = "\"{$type}\" (field " . (string)($field["id"] ?? "?") . ")";
				}
			}

			if (!$bad) {

				return null;
			}

			sort($valid);

			return "Unknown field type(s): " . implode(", ", array_unique($bad))
				. ". Available callout field types: " . implode(", ", $valid) . ".";
		}

		/**
		 * Resolve the callout's `display_field` — the field whose value labels each
		 * callout instance in the page editor.
		 *
		 * This was free text that nothing checked, so a model naming a field that
		 * didn't exist produced callout instances listing as blank rows until a human
		 * fixed the definition. An unsupplied value now defaults to the first text
		 * field, matching the developer UI's own convention.
		 *
		 * @param mixed $requested
		 * @param list<array<string,mixed>> $fields
		 * @return array<string,mixed> ["value" => string] or ["error" => string]
		 */
		private function aiResolveDisplayField($requested, array $fields): array {
			$ids = [];

			foreach ($fields as $field) {
				$field_id = (string)($field["id"] ?? "");

				if ($field_id !== "") {
					$ids[] = $field_id;
				}
			}

			$requested = trim((string)$requested);

			if ($requested !== "") {
				if (!in_array($requested, $ids, true)) {

					return ["error" => "display_field \"{$requested}\" is not one of this callout's fields"
						. ($ids ? " (" . implode(", ", $ids) . ")" : " — the callout has no fields") . "."];
				}

				return ["value" => $requested];
			}

			foreach ($fields as $field) {
				if ((string)($field["type"] ?? "") === "text" && (string)($field["id"] ?? "") !== "") {

					return ["value" => (string)$field["id"]];
				}
			}

			return ["value" => $ids ? $ids[0] : ""];
		}

	}
