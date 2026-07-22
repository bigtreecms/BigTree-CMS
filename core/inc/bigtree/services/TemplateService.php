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
	use BigTree\Api\TemplateScaffold;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\Tools\TemplateToolBackend;
	use BigTree;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use SQL;

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

			// Legacy's developer UI scaffolded the render file on create; without it a
			// template can be assigned to pages that then render nothing. A write
			// failure is surfaced on the response but never unwinds the record.
			$scaffolded = "";

			try {
				$scaffolded = TemplateScaffold::template(
					$id,
					is_array($insert["resources"] ?? null) ? $insert["resources"] : [],
					!empty($insert["routed"])
				);
			} catch (\Throwable $e) {
				$scaffolded = "";
			}

			$body = $this->present(BigTreeJSONDB::get("templates", $id));
			$body["scaffolded_file"] = $scaffolded;

			return Response::created($body, null);
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

			$raw_id = trim((string)($args["id"] ?? ""));

			if ($raw_id === "") {

				return ["error" => "A template id is required (a short lowercase identifier, e.g. \"landing-page\")."];
			}

			// Checked *after* urlify, not before: urlify returns "" for input with no
			// slug-able characters, so the emptiness check on the raw string let a
			// nonsense id stage with a nonsense summary and then die at approval with
			// "That template id is no longer available."
			$id = BigTreeCMS::urlify($raw_id);

			if ($id === "") {

				return ["error" => "\"{$raw_id}\" doesn't contain any characters usable in a template id. Use a short "
					. "identifier made of letters, numbers or hyphens (e.g. \"landing-page\")."];
			}

			if (BigTreeJSONDB::exists("templates", $id)) {

				return ["error" => "A template with the id \"{$id}\" already exists."];
			}

			$name = trim((string)($args["name"] ?? "")) ?: $id;
			$level = (int)($args["level"] ?? 0);
			$routed = !empty($args["routed"]);

			// REST declares level as in:0,1,2. Anything else compares as
			// higher-than-everyone in the level checks, so the template silently
			// becomes unusable by every user rather than erroring anywhere.
			if ($level < 0 || $level > 2) {

				return ["error" => "A template's level must be 0 (any editor), 1 (administrators) or 2 (developers). "
					. "\"{$level}\" would make the template unusable by everyone."];
			}
			$fields = $this->aiCleanResourceFields($args["fields"] ?? []);
			$type_error = $this->aiInvalidFieldTypeError($fields)
				?? Resources::aiUnconfigurableFieldError($args["fields"] ?? [], [], "template");

			if ($type_error !== null) {

				return ["error" => $type_error];
			}

			// Fail here rather than half-way through an approved write.
			if (!TemplateScaffold::templateIsWritable($id, $routed)) {

				return ["error" => "The templates/" . ($routed ? "routed" : "basic") . "/ directory is not writable, "
					. "so the template's render file can't be created. Fix the directory permissions and try again."];
			}

			// Asked of the scaffold rather than rebuilt here, so the path on the card
			// is exactly the path that will be written (a routed template's stub lives
			// at routed/{id}/default.php).
			$stub = TemplateScaffold::templatePath($id, $routed);
			$payload = [
				"id" => $id,
				"name" => $name,
				"level" => $level,
				"routed" => $routed,
				"resources" => $fields,
			];

			return [
				"ok" => true,
				"summary" => "Create a new page template “{$name}” (id {$id}) with " . count($fields) . " field(s). "
					. "A starter render file will be created at {$stub}.",
				"preview" => [
					"action" => "create_template",
					"id" => $id,
					"name" => $name,
					"level" => $level,
					"routed" => $routed,
					"fields" => $this->aiPreviewFields($fields),
					"creates_file" => $stub,
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

			$level = (int)($payload["level"] ?? 0);

			// Re-checked at approval like every other staged value — the payload sits
			// between staging and approval and isn't trusted on the way back out.
			if ($level < 0 || $level > 2) {

				return ["mode" => "error", "message" => "A template's level must be 0, 1 or 2."];
			}

			// An extension registering (or un-registering) a field type inside the
			// proposal's 24h TTL can change this verdict, so it is re-asked here like
			// every other staged value.
			$resources = is_array($payload["resources"] ?? null) ? $payload["resources"] : [];
			$type_error = $this->aiInvalidFieldTypeError($resources)
				?? Resources::aiUnconfigurableFieldError($resources, [], "template");

			if ($type_error !== null) {

				return ["mode" => "error", "message" => $type_error];
			}

			$insert = [
				"id" => $id,
				"name" => BigTree::safeEncode((string)($payload["name"] ?? $id)),
				"module" => "",
				"resources" => Resources::clean($resources),
				"level" => $level,
				"routed" => Flag::checkbox(!empty($payload["routed"])),
				"hooks" => [],
				"position" => 0,
			];

			BigTreeJSONDB::incrementPosition("templates");
			BigTreeJSONDB::insert("templates", $insert);

			$scaffolded = "";
			$scaffold_error = "";

			try {
				$scaffolded = TemplateScaffold::template($id, $insert["resources"], !empty($payload["routed"]));
			} catch (\Throwable $e) {
				$scaffold_error = $e->getMessage();
			}

			return [
				"mode" => "created",
				"id" => $id,
				"name" => (string)($payload["name"] ?? $id),
				"file" => $scaffolded,
				"note" => $scaffolded !== ""
					? "A starter render file was created at {$scaffolded} — edit it to control how pages using this "
						. "template look."
					: ($scaffold_error !== ""
						? "The template record was created, but its render file could not be written ({$scaffold_error}). "
							. "Pages using this template will render nothing until the file exists."
						: "A render file already existed for this template and was left untouched."),
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

				// Same constraint the create path enforces — an out-of-range level
				// leaves the template usable by nobody without erroring anywhere.
				if ($level < 0 || $level > 2) {

					return ["error" => "A template's level must be 0 (any editor), 1 (administrators) or "
						. "2 (developers). \"{$level}\" would make the template unusable by everyone."];
				}

				if ($level !== (int)($existing["level"] ?? 0)) {
					$changes["level"] = $level;
					$diff["level"] = ["from" => (int)($existing["level"] ?? 0), "to" => $level];
				}
			}

			if (array_key_exists("fields", $args)) {
				$before = is_array($existing["resources"] ?? null) ? $existing["resources"] : [];
				$fields = $this->aiCleanResourceFields($args["fields"], $before);
				$type_error = $this->aiInvalidFieldTypeError($fields)
					?? Resources::aiUnconfigurableFieldError($args["fields"], $before, "template");

				if ($type_error !== null) {

					return ["error" => $type_error];
				}

				// The raw list is what's staged: the stored resources can change during
				// the proposal's TTL, so the merge is redone against them at approval.
				$changes["fields"] = $args["fields"];
				$diff["fields"] = ["from" => count($before), "to" => count($fields)];
				$diff = array_merge($diff, $this->aiTemplateFieldDiff($before, $fields, $id));
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
				// The whole record: an edit to the field list staged against one
				// definition must not be applied to a different one.
				"fingerprint" => ["type" => "json_record", "store" => "templates", "id" => $id],
			];
		}

		/**
		 * Describe what replacing a template's field list actually does, by name.
		 *
		 * The proposal card is the AI user's only view of this change — REST has an
		 * editing UI showing the full list, this has a summary line. A bare field
		 * *count* hid the two things that matter: dropping a field orphans its content
		 * on every page using the template, and retyping one discards the settings
		 * configured for its old type, which can include its required rule.
		 *
		 * Rendered through the existing `changes` diff shape, so the card needs no
		 * new preview handling.
		 *
		 * @param list<array<string,mixed>> $before
		 * @param list<array<string,mixed>> $after
		 * @return array<string,mixed> Extra `changes` rows.
		 */
		private function aiTemplateFieldDiff(array $before, array $after, string $template_id): array {
			$old = $this->aiIndexResources($before);
			$new = $this->aiIndexResources($after);
			$added = array_values(array_diff(array_keys($new), array_keys($old)));
			$removed = array_values(array_diff(array_keys($old), array_keys($new)));
			$retyped = [];
			$unrequired = [];
			$newly_required = [];

			foreach ($new as $field_id => $field) {
				if (!isset($old[$field_id])) {
					if ($this->aiResourceIsRequired($field)) {
						$newly_required[] = $field_id;
					}

					continue;
				}

				$old_type = (string)($old[$field_id]["type"] ?? "");
				$new_type = (string)($field["type"] ?? "");

				if ($old_type !== $new_type) {
					$retyped[] = "{$field_id} ({$old_type} → {$new_type})";
				}

				if ($this->aiResourceIsRequired($old[$field_id]) && !$this->aiResourceIsRequired($field)) {
					$unrequired[] = $field_id;
				}
			}

			$rows = [];
			$page_count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages WHERE template = ?", $template_id);

			if ($added) {
				$rows["fields_added"] = implode(", ", $added);
			}

			if ($removed) {
				$rows["fields_removed"] = implode(", ", $removed)
					. ($page_count > 0
						? " — existing content in " . (count($removed) === 1 ? "this field" : "these fields")
							. " is orphaned on " . $page_count . " page" . ($page_count === 1 ? "" : "s")
						: "");
			}

			if ($retyped) {
				$rows["fields_retyped"] = implode(", ", $retyped);
			}

			if ($newly_required) {
				$rows["now_required"] = implode(", ", $newly_required);
			}

			if ($unrequired) {
				$rows["no_longer_required"] = implode(", ", $unrequired)
					. " — changing a field's type discards the settings configured for its old type";
			}

			$rows["pages_using_template"] = $page_count;

			return $rows;
		}

		/**
		 * @param list<array<string,mixed>> $resources
		 * @return array<string,array<string,mixed>>
		 */
		private function aiIndexResources(array $resources): array {
			$indexed = [];

			foreach ($resources as $resource) {
				$id = is_array($resource) ? (string)($resource["id"] ?? "") : "";

				if ($id !== "") {
					$indexed[$id] = $resource;
				}
			}

			return $indexed;
		}

		/**
		 * @param array<string,mixed> $resource
		 */
		private function aiResourceIsRequired(array $resource): bool {
			$settings = is_array($resource["settings"] ?? null) ? $resource["settings"] : [];
			$validation = (string)($settings["validation"] ?? "");

			return in_array("required", preg_split("/\s+/", trim($validation), -1, PREG_SPLIT_NO_EMPTY) ?: [], true);
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
				$level = (int)$changes["level"];

				if ($level < 0 || $level > 2) {

					return ["mode" => "error", "message" => "A template's level must be 0, 1 or 2."];
				}

				$next["level"] = $level;
			}

			// Re-merged here rather than reused from the proposal: the template's fields
			// may have been edited in the admin during the proposal's TTL, and those
			// settings have to survive the approval too.
			if (array_key_exists("fields", $changes)) {
				$before = is_array($existing["resources"] ?? null) ? $existing["resources"] : [];
				$merged = $this->aiCleanResourceFields($changes["fields"], $before);

				// Only the staging pass used to check the types, so an extension
				// uninstalled inside the TTL wrote its now-unknown type verbatim.
				$type_error = $this->aiInvalidFieldTypeError($merged)
					?? Resources::aiUnconfigurableFieldError($changes["fields"], $before, "template");

				if ($type_error !== null) {

					return ["mode" => "error", "message" => $type_error];
				}

				$next["resources"] = $merged;
			} elseif (array_key_exists("resources", $changes)) {
				$resources = is_array($changes["resources"]) ? $changes["resources"] : [];
				$type_error = $this->aiInvalidFieldTypeError($resources)
					?? Resources::aiUnconfigurableFieldError($resources, [], "template");

				if ($type_error !== null) {

					return ["mode" => "error", "message" => $type_error];
				}

				$next["resources"] = Resources::clean($resources);
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
		 * shape, merged against what the template already stores so that a field the
		 * model copied over unchanged keeps its settings. Fields without an id are
		 * dropped by Resources::clean.
		 *
		 * @param mixed $fields
		 * @param list<array<string,mixed>> $existing Stored resources ([] when creating).
		 * @return list<array<string,mixed>>
		 */
		private function aiCleanResourceFields($fields, array $existing = []): array {

			return Resources::mergeAiFields($fields, $existing);
		}

		/**
		 * Reject any field whose `type` isn't an installed template field type.
		 * Nothing checked this before, so a model-invented type ("richtext",
		 * "wysiwyg") was stored verbatim and rendered as a broken field in the page
		 * editor — with no error anywhere to explain why.
		 *
		 * Returns null when every type is valid, else a recoverable error listing the
		 * offenders and the types actually available.
		 *
		 * @param list<array<string,mixed>> $fields
		 */
		private function aiInvalidFieldTypeError(array $fields): ?string {
			$valid = FieldTypeService::availableFieldTypeIds("templates");

			// An empty catalog means the field-type registry couldn't be resolved —
			// don't turn that into a refusal of every field.
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
				. ". Available template field types: " . implode(", ", $valid) . ".";
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
