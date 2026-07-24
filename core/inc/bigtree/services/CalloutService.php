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
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Services\AI\Tools\CalloutToolBackend;
	use BigTree;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use SQL;

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
			$this->assertNoReservedFieldIds($d["resources"] ?? []);
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
			$this->assertNoReservedFieldIds($d["resources"] ?? []);
			$next = array_merge($existing, FieldSpec::update($d, self::FIELDS));
			BigTreeJSONDB::update("callouts", $id, $next);

			return Response::ok($this->present(BigTreeJSONDB::get("callouts", $id)));
		}

		/**
		 * Refuse a callout whose fields claim an id the placed instance owns. Legacy
		 * refused this in the developer UI (admin.php:998); the REST rewrite lost the
		 * guard, and a field called `type` makes every callout of that type render as
		 * nothing on every page it's placed on.
		 *
		 * @param mixed $resources
		 * @throws BadRequestException
		 */
		private function assertNoReservedFieldIds($resources): void {
			$reserved = Resources::reservedCalloutFieldIds($resources);

			if ($reserved) {
				throw new BadRequestException(
					"A callout field can't be named \"" . implode("\" or \"", $reserved) . "\" — that key belongs "
						. "to the placed callout itself.",
					"reserved_field_id"
				);
			}
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
		 * The column caps routes/callouts.php declares. Enforced here too: nothing in
		 * the AI seams checked them, so a 400-character id passed validation, inserted
		 * the record, then failed the render-file write — leaving a callout that
		 * renders nothing and a proposal reported as successful.
		 */
		private const AI_CALLOUT_MAX_LENGTHS = [
			"id" => 127,
			"name" => 255,
			"description" => 1024,
			"display_field" => 255,
			"display_default" => 255,
		];

		// The max:255 the POST /callout-groups route enforces on the group name; the
		// two group creators were the only create seams left without a length cap
		// (audit #7 B4).
		private const AI_GROUP_NAME_MAX_LENGTH = 255;

		// How far the callout usage scan counts before it stops. A blast-radius figure
		// on a proposal card only has to answer "a handful or the whole site?", and the
		// scan is an unindexed LIKE over every page's resource blob on a staging path.
		private const AI_CALLOUT_USAGE_LIMIT = 200;

		/**
		 * The first over-length callout value, as a recoverable error. Shared by
		 * staging and approval so a stored payload can't slip past.
		 *
		 * @param array<string,mixed> $fields
		 */
		private function aiCalloutLengthError(array $fields): ?string {
			foreach (self::AI_CALLOUT_MAX_LENGTHS as $field => $max) {
				if (!array_key_exists($field, $fields)) {

					continue;
				}

				$length = mb_strlen((string)$fields[$field]);

				if ($length > $max) {

					return "The callout's {$field} is {$length} characters, but the field holds at most {$max}. "
						. "Shorten it and try again.";
				}
			}

			return null;
		}

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateCalloutCreate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can create callouts."];
			}

			$raw_id = trim((string)($args["id"] ?? ""));

			if ($raw_id === "") {

				return ["error" => "A callout id is required (a short lowercase identifier)."];
			}

			// Checked after urlify, for the same reason the template path does it: an
			// id with no slug-able characters urlifies to "" and would otherwise stage
			// happily and die at approval.
			$id = BigTreeCMS::urlify($raw_id);

			if ($id === "") {

				return ["error" => "\"{$raw_id}\" doesn't contain any characters usable in a callout id. Use a short "
					. "identifier made of letters, numbers or hyphens."];
			}

			if (BigTreeJSONDB::exists("callouts", $id)) {

				return ["error" => "A callout with the id \"{$id}\" already exists."];
			}

			$name = trim((string)($args["name"] ?? "")) ?: $id;
			$level = (int)($args["level"] ?? 0);

			// REST declares level as in:0,1,2 (routes/callouts.php). Anything else
			// compares as higher-than-everyone, so the callout is offered to nobody —
			// including its author. Same clamp templates got in audit #4.
			if ($level < 0 || $level > 2) {

				return ["error" => "A callout's level must be 0 (any editor), 1 (administrators) or 2 (developers). "
					. "\"{$level}\" would make the callout usable by nobody."];
			}

			$fields = $this->aiCalloutFields($args["fields"] ?? []);
			$type_error = Resources::aiFieldIdError($args["fields"] ?? [], "callout")
				?? $this->aiInvalidFieldTypeError($fields)
				?? Resources::aiUnconfigurableFieldError($args["fields"] ?? [], [], "callout");

			if ($type_error !== null) {

				return ["error" => $type_error];
			}

			$display_field = $this->aiResolveDisplayField($args["display_field"] ?? "", $fields);

			if (isset($display_field["error"])) {

				return $display_field;
			}

			$group = $this->aiResolveCalloutGroup($args["group"] ?? "");

			if (isset($group["needs_input"])) {

				return $group;
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
				"level" => $level,
				"display_field" => $display_field["value"],
				"display_default" => trim((string)($args["display_default"] ?? "")),
				"resources" => $fields,
				"group" => $group["id"],
			];

			$too_long = $this->aiCalloutLengthError($payload);

			if ($too_long !== null) {

				return ["error" => $too_long];
			}

			// A callout outside every group is invisible in a callouts field restricted
			// to one — it "exists" and is unusable exactly where it was wanted. Say so
			// on the card rather than letting the developer discover it later.
			$group_note = $group["id"] !== ""
				? " It will be added to the “{$group["name"]}” callout group."
				: " It won't belong to any callout group, so a page region restricted to a group won't offer it.";

			return [
				"ok" => true,
				"summary" => "Create a new callout “{$name}” (id {$id}) with " . count($fields) . " field(s). "
					. "A starter render file will be created at {$stub}." . $group_note,
				"preview" => [
					"action" => "create_callout",
					"id" => $id,
					"name" => $name,
					"level" => $level,
					"display_field" => $display_field["value"],
					"group" => $group["id"] !== "" ? $group["name"] : "(none)",
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

			$level = (int)($payload["level"] ?? 0);

			// Re-checked at approval like every other staged value.
			if ($level < 0 || $level > 2) {

				return ["mode" => "error", "message" => "A callout's level must be 0, 1 or 2."];
			}

			$resources = is_array($payload["resources"] ?? null) ? $payload["resources"] : [];
			$type_error = Resources::aiFieldIdError($resources, "callout")
				?? $this->aiInvalidFieldTypeError($resources)
				?? Resources::aiUnconfigurableFieldError($resources, [], "callout");

			if ($type_error !== null) {

				return ["mode" => "error", "message" => $type_error];
			}

			// Re-asked at approval like every other staged value.
			$too_long = $this->aiCalloutLengthError($payload);

			if ($too_long !== null) {

				return ["mode" => "error", "message" => $too_long];
			}

			$insert = [
				"id" => $id,
				"name" => BigTree::safeEncode((string)($payload["name"] ?? $id)),
				"description" => BigTree::safeEncode((string)($payload["description"] ?? "")),
				"level" => $level,
				"resources" => Resources::clean($resources),
				"display_field" => (string)($payload["display_field"] ?? ""),
				"display_default" => BigTree::safeEncode((string)($payload["display_default"] ?? "")),
				"position" => 0,
			];

			BigTreeJSONDB::incrementPosition("callouts");
			BigTreeJSONDB::insert("callouts", $insert);

			$group_id = (string)($payload["group"] ?? "");
			$grouped = $this->aiAddCalloutToGroup($group_id, $id);

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
				"group" => $grouped ? $group_id : "",
				"note" => ($scaffolded !== ""
					? "A starter render file was created at {$scaffolded} — edit it to control how this callout looks."
					: ($scaffold_error !== ""
						? "The callout record was created, but its render file could not be written ({$scaffold_error}). "
							. "Pages using this callout will render nothing until the file exists."
						: "A render file already existed for this callout and was left untouched."))
					. ($group_id !== "" && !$grouped
						? " The callout group it was meant to join no longer exists, so it was created ungrouped."
						: ""),
			];
		}

		/**
		 * Read one callout's definition, including its full field list.
		 *
		 * Pairs with update_callout: supplying `fields` there replaces the whole list,
		 * so the assistant has to be able to see what's already on the callout before
		 * proposing a change to it — otherwise "add a field" means guessing the rest.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetCallout(string $callout_id, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can inspect callout definitions."];
			}

			$callout_id = trim($callout_id);
			$callout = $callout_id !== "" ? BigTreeJSONDB::get("callouts", $callout_id) : null;

			if (!$callout) {

				return ["error" => "Callout \"{$callout_id}\" does not exist."];
			}

			$fields = [];

			foreach ((array)($callout["resources"] ?? []) as $resource) {
				$settings = is_array($resource["settings"] ?? null) ? $resource["settings"] : [];

				$fields[] = [
					"id" => (string)($resource["id"] ?? ""),
					"type" => (string)($resource["type"] ?? ""),
					"title" => (string)($resource["title"] ?? ""),
					"subtitle" => (string)($resource["subtitle"] ?? ""),
					// Read-only: update_callout preserves a retained field's settings
					// rather than accepting them back, but the assistant still has to be
					// able to see that a field carries configuration it isn't editing.
					"settings" => $settings,
					"required" => $this->aiResourceIsRequired(is_array($resource) ? $resource : []),
				];
			}

			return [
				"callout" => [
					"id" => (string)$callout["id"],
					"name" => (string)($callout["name"] ?? ""),
					"description" => (string)($callout["description"] ?? ""),
					"level" => (int)($callout["level"] ?? 0),
					"display_field" => (string)($callout["display_field"] ?? ""),
					"display_default" => (string)($callout["display_default"] ?? ""),
					"fields" => $fields,
					// create_callout can file a callout in a group but nothing could read
					// which groups a callout is in, so membership was write-only.
					"groups" => $this->aiCalloutGroupsFor((string)$callout["id"]),
				],
				// The valid `fields[].type` ids for update_callout/create_callout, so the
				// model authors against a real catalog rather than guessing (B1).
				"field_types" => FieldTypeService::aiFieldTypeCatalog("callouts"),
			];
		}

		/**
		 * The callout groups a callout belongs to, as {id, name}.
		 *
		 * @return list<array<string,string>>
		 */
		private function aiCalloutGroupsFor(string $callout_id): array {
			$groups = [];

			foreach (BigTreeJSONDB::getAll("callout-groups", "name") as $group) {
				$members = array_map("strval", (array)($group["callouts"] ?? []));

				if (in_array($callout_id, $members, true)) {
					$groups[] = [
						"id" => (string)($group["id"] ?? ""),
						"name" => (string)($group["name"] ?? $group["id"] ?? ""),
					];
				}
			}

			return $groups;
		}

		/**
		 * Every callout and callout group the assistant can name.
		 *
		 * list_templates exists precisely so the model can name a real template; the
		 * callout surface had no equivalent, so get_callout needed a guessed id and
		 * create_callout's `group` argument had nothing to enumerate — which made its
		 * needs_input branch the normal path rather than the exception.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiListCallouts($user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can list callouts."];
			}

			$membership = [];

			$groups = array_map(function (array $group) use (&$membership): array {
				$id = (string)($group["id"] ?? "");
				$members = array_map("strval", (array)($group["callouts"] ?? []));

				foreach ($members as $callout_id) {
					$membership[$callout_id][] = $id;
				}

				return [
					"id" => $id,
					"name" => (string)($group["name"] ?? $id),
					"callouts" => $members,
				];
			}, BigTreeJSONDB::getAll("callout-groups", "name"));

			$callouts = array_map(function (array $callout) use ($membership): array {
				$id = (string)($callout["id"] ?? "");

				return [
					"id" => $id,
					"name" => (string)($callout["name"] ?? $id),
					"description" => (string)($callout["description"] ?? ""),
					"level" => (int)($callout["level"] ?? 0),
					"field_count" => is_array($callout["resources"] ?? null) ? count($callout["resources"]) : 0,
					"groups" => $membership[$id] ?? [],
				];
			}, BigTreeJSONDB::getAll("callouts", "position", "DESC"));

			return [
				"callouts" => $callouts,
				"groups" => $groups,
				// The valid `fields[].type` ids for create_callout, so a callout authored
				// from scratch (with no existing one to read via get_callout) names a real
				// type rather than guessing (B1). This seam is developer-gated already.
				"field_types" => FieldTypeService::aiFieldTypeCatalog("callouts"),
			];
		}

		/**
		 * Validate an edit to an existing callout: name/description/level/display and,
		 * most usefully, its field list — "add a field to the promo callout" was the
		 * most likely developer ask and had no path at all (create only).
		 *
		 * Only the keys actually supplied are changed, so a name edit can't silently
		 * drop the callout's fields. The render file is never touched: it's authored
		 * code, and rewriting it would discard the developer's markup.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateCalloutUpdate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can edit callouts."];
			}

			$id = trim((string)($args["id"] ?? ""));
			$existing = $id !== "" ? BigTreeJSONDB::get("callouts", $id) : null;

			if (!$existing) {

				return ["error" => "Callout \"{$id}\" does not exist."];
			}

			$changes = [];
			$diff = [];

			foreach (["name", "description", "display_default"] as $field) {
				if (array_key_exists($field, $args)) {
					$value = trim((string)$args[$field]);

					if ($value !== (string)($existing[$field] ?? "")) {
						$changes[$field] = $value;
						$diff[$field] = ["from" => (string)($existing[$field] ?? ""), "to" => $value];
					}
				}
			}

			if (array_key_exists("level", $args)) {
				$level = (int)$args["level"];

				// Same constraint the create path enforces.
				if ($level < 0 || $level > 2) {

					return ["error" => "A callout's level must be 0 (any editor), 1 (administrators) or "
						. "2 (developers). \"{$level}\" would make the callout usable by nobody."];
				}

				if ($level !== (int)($existing["level"] ?? 0)) {
					$changes["level"] = $level;
					$diff["level"] = ["from" => (int)($existing["level"] ?? 0), "to" => $level];
				}
			}

			$fields = is_array($existing["resources"] ?? null) ? $existing["resources"] : [];

			if (array_key_exists("fields", $args)) {

				// "fields" replaces the whole list, so an empty list empties the callout
				// and orphans the content stored under its keys everywhere it is used.
				// Refuse it rather than disclose a total wipe as an ordinary diff.
				if (is_array($args["fields"]) && !$args["fields"]) {

					return ["error" => "An empty field list would remove every field on the callout and orphan the "
						. "content stored under them. To keep the callout but change its fields, include the fields it "
						. "should end up with; to remove every field, do it in Developer → Callouts."];
				}

				$before = is_array($existing["resources"] ?? null) ? $existing["resources"] : [];
				$fields = $this->aiCalloutFields($args["fields"], $before);
				$type_error = Resources::aiFieldIdError($args["fields"], "callout")
					?? $this->aiInvalidFieldTypeError($fields)
					?? Resources::aiUnconfigurableFieldError($args["fields"], $before, "callout");

				if ($type_error !== null) {

					return ["error" => $type_error];
				}

				// The raw list is staged, not the merged one: the callout's fields can be
				// edited during the proposal's TTL, so the merge is redone at approval.
				$changes["fields"] = $args["fields"];
				$diff = array_merge($diff, $this->aiCalloutFieldDiff($before, $fields, $id));
			}

			// display_field must name a field that will still exist after this edit,
			// so it is resolved against the resulting list rather than the stored one.
			if (array_key_exists("display_field", $args)) {
				$display_field = $this->aiResolveDisplayField($args["display_field"], $fields);

				if (isset($display_field["error"])) {

					return $display_field;
				}

				if ($display_field["value"] !== (string)($existing["display_field"] ?? "")) {
					$changes["display_field"] = $display_field["value"];
					$diff["display_field"] = [
						"from" => (string)($existing["display_field"] ?? ""),
						"to" => $display_field["value"],
					];
				}
			} elseif (array_key_exists("fields", $changes)) {
				// The field list changed under a display_field that wasn't re-stated;
				// if it named a field that's now gone, the callout would show nothing.
				$current_display = (string)($existing["display_field"] ?? "");
				$still_present = false;

				foreach ($fields as $field) {
					if ((string)($field["id"] ?? "") === $current_display) {
						$still_present = true;

						break;
					}
				}

				if ($current_display !== "" && !$still_present) {

					return ["error" => "This callout's display field is \"{$current_display}\", which isn't in the new "
						. "field list. Include it, or pass display_field to choose a different one."];
				}
			}

			// Group membership was settable at create and nowhere afterwards, so
			// "actually, put the promo callout in the sidebar group" had no path.
			if (array_key_exists("group", $args)) {
				$group = $this->aiResolveCalloutGroup($args["group"]);

				if (isset($group["needs_input"])) {

					return $group;
				}

				$current = array_column($this->aiCalloutGroupsFor($id), "id");

				if ($group["id"] === "" ? (bool)$current : !in_array($group["id"], $current, true)) {
					$changes["group"] = $group["id"];
					$diff["group"] = [
						"from" => $current ? implode(", ", $current) : "(none)",
						"to" => $group["id"] !== "" ? $group["name"] : "(none)",
					];
				}
			}

			if (!$changes) {

				return ["error" => "No changes were supplied — nothing to update."];
			}

			$too_long = $this->aiCalloutLengthError($changes);

			if ($too_long !== null) {

				return ["error" => $too_long];
			}

			$name = (string)($existing["name"] ?? $id);

			return [
				"ok" => true,
				"summary" => "Update callout “{$name}” (id {$id}). Its render file is not changed.",
				"preview" => [
					"action" => "update_callout",
					"id" => $id,
					"name" => $name,
					"changes" => $diff,
				],
				"payload" => [
					"id" => $id,
					"changes" => $changes,
				],
				"fingerprint" => ["type" => "json_record", "store" => "callouts", "id" => $id],
			];
		}

		/**
		 * Name what replacing a callout's field list does, the way update_template
		 * does: a dropped field orphans its content in every callout instance already
		 * placed on a page, which a bare count would hide.
		 *
		 * @param list<array<string,mixed>> $before
		 * @param list<array<string,mixed>> $after
		 * @return array<string,mixed> Extra `changes` rows.
		 */
		private function aiCalloutFieldDiff(array $before, array $after, string $callout_id = ""): array {
			$old = [];
			$new = [];

			foreach ($before as $field) {
				$old[(string)($field["id"] ?? "")] = $field;
			}

			foreach ($after as $field) {
				$new[(string)($field["id"] ?? "")] = $field;
			}

			unset($old[""], $new[""]);

			$added = array_values(array_diff(array_keys($new), array_keys($old)));
			$removed = array_values(array_diff(array_keys($old), array_keys($new)));
			$retyped = [];
			$newly_required = [];
			$unrequired = [];

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

				// The mirror of the line above, and the case the now_required row was
				// written for: a field that already exists and has just been made
				// required is the one every existing placement is most likely to be
				// holding empty. Only fields *new* to the callout were collected, so the
				// disclosure never fired for it.
				if (!$this->aiResourceIsRequired($old[$field_id]) && $this->aiResourceIsRequired($field)) {
					$newly_required[] = $field_id;
				}
			}

			$rows = ["fields" => ["from" => count($old), "to" => count($new)]];
			$usage = $this->aiCalloutPageUsage($callout_id);

			if ($added) {
				$rows["fields_added"] = implode(", ", $added);
			}

			if ($removed) {
				$rows["fields_removed"] = implode(", ", $removed)
					. " — existing content in " . (count($removed) === 1 ? "this field" : "these fields")
					. " is orphaned wherever this callout is already placed"
					. ($usage !== "" ? " ({$usage})" : "");
			}

			if ($retyped) {
				$rows["fields_retyped"] = implode(", ", $retyped);
			}

			if ($newly_required) {
				// The template diff had the same silence: a newly required field has no
				// value on placements that predate it, so the admin's own save refuses
				// them — including records this edit never touched. Naming the
				// consequence is the pattern fields_removed already follows. Worded to
				// hold for both ways a field becomes required: one added to the callout
				// (empty on every placement by definition) and one that already existed
				// and was flipped (empty on an unknown share of them).
				$rows["now_required"] = implode(", ", $newly_required)
					. " — a placed callout with " . (count($newly_required) === 1 ? "this field" : "these fields")
					. " empty can't be saved again until " . (count($newly_required) === 1 ? "it is" : "they are")
					. " filled in, including on pages this edit never touched"
					. ($usage !== "" ? " ({$usage})" : "");
			}

			if ($unrequired) {
				$rows["no_longer_required"] = implode(", ", $unrequired)
					. " — changing a field's type discards the settings configured for its old type";
			}

			if ($usage !== "") {
				$rows["pages_using_callout"] = $usage;
			}

			return $rows;
		}

		/**
		 * Roughly how widely a callout is placed, as a card-ready phrase.
		 *
		 * The template diff counts the pages on a template exactly (one indexed column
		 * compare) and puts the number on the card; the callout diff said only
		 * "orphaned wherever this callout is already placed" — the abstract consequence
		 * with no sense of scale, and a callout is typically embedded in far more
		 * places than a template is applied to. Silence there reads as "small".
		 *
		 * Exactness isn't available cheaply: placements live inside the `callouts`
		 * field's JSON in bigtree_pages.resources and in module entry columns, so a
		 * real count means walking every page's resource blob and every module table.
		 * A bounded LIKE over the pages blob is honest about being a floor, costs one
		 * query, and turns an abstraction into a number (audit #9 C1/D5).
		 *
		 * Returns "" when the count can't be taken, so the caller omits the phrase
		 * rather than claiming zero.
		 */
		private function aiCalloutPageUsage(string $callout_id): string {
			if ($callout_id === "") {

				return "";
			}

			try {
				// A placed callout is stored as {"type":"<callout id>", …} inside the
				// page's resources JSON (see the callouts field type's process.php).
				// Callout ids are letters, numbers and hyphens, so there is nothing in
				// one for LIKE to interpret as a wildcard.
				//
				// Both spacings have to be matched: the new API writes the column with a
				// bare json_encode(), the legacy admin writes it through BigTree::json(),
				// which is JSON_PRETTY_PRINT — so the same placement is stored as
				// "type":"promo" on one row and "type": "promo" on the next, and real
				// databases hold a mix. Matching only the compact form under-counted, and
				// a callout placed solely on legacy-written pages counted zero, which
				// omits the phrase entirely — the silence this exists to break.
				$count = (int)SQL::fetchSingle(
					"SELECT COUNT(*) FROM (SELECT id FROM bigtree_pages WHERE resources LIKE ? OR resources LIKE ? "
						. "LIMIT " . self::AI_CALLOUT_USAGE_LIMIT . ") counted",
					'%"type":"' . $callout_id . '"%',
					'%"type": "' . $callout_id . '"%'
				);
			// Deliberately \Exception and not \Throwable: the catch exists for a
			// database that can't answer (an unindexed scan of a longtext column on a
			// staging path is the one query here that can time out), and a proposal
			// should still stage without the figure. Catching Error as well swallowed a
			// missing `use SQL;` in this file, so the count silently returned "" — the
			// disclosure reported as absent, which reads as "not used anywhere".
			} catch (\Exception $e) {

				return "";
			}

			if ($count === 0) {

				return "";
			}

			// "at least", never a bare number: module entry columns hold callouts too
			// and aren't counted, and the scan stops at the cap.
			return "used on at least {$count} page" . ($count === 1 ? "" : "s")
				. ($count >= self::AI_CALLOUT_USAGE_LIMIT ? " (counting stopped there)" : "")
				. "; module entries aren't counted";
		}

		/**
		 * Execute an approved callout edit. Re-checks developer level and that the
		 * callout still exists; the render file is deliberately left alone.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdateCallout(array $payload, $user): array {
			if (PermissionService::level($user) < 2) {
				throw new AuthorizationException("Only developers can edit callouts.");
			}

			$id = (string)($payload["id"] ?? "");
			$existing = $id !== "" ? BigTreeJSONDB::get("callouts", $id) : null;

			if (!$existing) {

				return ["mode" => "error", "message" => "That callout no longer exists."];
			}

			$changes = is_array($payload["changes"] ?? null) ? $payload["changes"] : [];
			$update = $existing;

			foreach (["name", "description", "display_default"] as $field) {
				if (array_key_exists($field, $changes)) {
					$update[$field] = BigTree::safeEncode((string)$changes[$field]);
				}
			}

			// Re-asked at approval like every other staged value.
			$too_long = $this->aiCalloutLengthError($changes);

			if ($too_long !== null) {

				return ["mode" => "error", "message" => $too_long];
			}

			if (array_key_exists("level", $changes)) {
				$level = (int)$changes["level"];

				if ($level < 0 || $level > 2) {

					return ["mode" => "error", "message" => "A callout's level must be 0, 1 or 2."];
				}

				$update["level"] = $level;
			}

			// Re-merged against the callout as it stands now, so field settings edited in
			// the admin during the proposal's TTL survive the approval.
			if (array_key_exists("fields", $changes)) {
				$before = is_array($existing["resources"] ?? null) ? $existing["resources"] : [];
				$merged = $this->aiCalloutFields($changes["fields"], $before);

				// Only the staging pass used to check the types, so an extension
				// uninstalled inside the TTL wrote its now-unknown type verbatim.
				$type_error = Resources::aiFieldIdError($changes["fields"], "callout")
					?? $this->aiInvalidFieldTypeError($merged)
					?? Resources::aiUnconfigurableFieldError($changes["fields"], $before, "callout");

				if ($type_error !== null) {

					return ["mode" => "error", "message" => $type_error];
				}

				$update["resources"] = $merged;
			} elseif (array_key_exists("resources", $changes)) {
				// A payload carrying `resources` rather than `fields` — nothing stages
				// one today, but if anything ever does it must go through the same
				// merge, not straight to clean(): clean() rebuilds each field from what
				// it was handed, so a payload that restated a field without its
				// settings would silently strip the configuration a human set.
				$before = is_array($existing["resources"] ?? null) ? $existing["resources"] : [];
				$resources = is_array($changes["resources"]) ? $changes["resources"] : [];
				$merged = $this->aiCalloutFields($resources, $before);
				$type_error = Resources::aiFieldIdError($resources, "callout")
					?? $this->aiInvalidFieldTypeError($merged)
					?? Resources::aiUnconfigurableFieldError($resources, $before, "callout");

				if ($type_error !== null) {

					return ["mode" => "error", "message" => $type_error];
				}

				$update["resources"] = $merged;
			}

			// display_field has to name a field that exists in the *resulting* list.
			// Staging checked that; approval used to write the staged string blind, so
			// approving a field-list edit and then a display_field edit staged before
			// it left the callout pointing at a field that no longer exists — every
			// placed instance then lists as a blank row. Done after the field merge so
			// it judges the list actually being written.
			if (array_key_exists("display_field", $changes)) {
				$display_field = $this->aiResolveDisplayField(
					$changes["display_field"],
					is_array($update["resources"] ?? null) ? $update["resources"] : []
				);

				if (isset($display_field["error"])) {

					return ["mode" => "error", "message" => (string)$display_field["error"]];
				}

				$update["display_field"] = $display_field["value"];
			}

			BigTreeJSONDB::update("callouts", $id, $update);

			// Membership lives on the *group* records, so it is applied after the
			// callout itself and re-resolved here (a group can vanish inside the TTL).
			$group_note = "";

			if (array_key_exists("group", $changes)) {
				$group_id = (string)$changes["group"];
				$this->aiRemoveCalloutFromGroups($id, $group_id);

				if ($group_id !== "" && !$this->aiAddCalloutToGroup($group_id, $id)) {
					$group_note = " The callout group it was meant to join no longer exists, so it is now ungrouped.";
				}
			}

			return [
				"mode" => "updated",
				"id" => $id,
				"name" => (string)($update["name"] ?? $id),
				"note" => $group_note !== "" ? trim($group_note) : null,
			];
		}

		/**
		 * Drop a callout from every group except $keep. Moving a callout into a group
		 * means moving it *out* of the others — leaving it in both would offer the
		 * same callout twice in a region restricted to one of them.
		 */
		private function aiRemoveCalloutFromGroups(string $callout_id, string $keep): void {
			foreach (BigTreeJSONDB::getAll("callout-groups") as $group) {
				$group_id = (string)($group["id"] ?? "");

				if ($group_id === $keep) {

					continue;
				}

				$callouts = array_values(array_filter((array)($group["callouts"] ?? []), "is_string"));
				$position = array_search($callout_id, $callouts, true);

				if ($position === false) {

					continue;
				}

				unset($callouts[$position]);
				$group["callouts"] = array_values($callouts);
				BigTreeJSONDB::update("callout-groups", $group_id, $group);
			}
		}

		/**
		 * Resolve a requested callout group by id or name.
		 *
		 * create_callout had no group support at all, so an AI-created callout was
		 * orphaned from every group — and a page whose callouts field is restricted to
		 * a group would never offer it. A name that matches nothing returns the
		 * choice rather than an error, because "put it in the sidebar group" is a
		 * reasonable ask that shouldn't dead-end on an id the model can't know.
		 *
		 * @param mixed $requested
		 * @return array{id:string,name:string}|array{needs_input:array<string,mixed>}
		 */
		private function aiResolveCalloutGroup($requested): array {
			$requested = trim((string)$requested);

			if ($requested === "") {

				return ["id" => "", "name" => ""];
			}

			$groups = BigTreeJSONDB::getAll("callout-groups");

			foreach ($groups as $group) {
				$id = (string)($group["id"] ?? "");
				$name = (string)($group["name"] ?? "");

				if ($id === $requested || strcasecmp($name, $requested) === 0) {

					return ["id" => $id, "name" => $name !== "" ? $name : $id];
				}
			}

			$options = array_map(function (array $group): array {

				return [
					"id" => (string)($group["id"] ?? ""),
					"label" => (string)($group["name"] ?? $group["id"] ?? ""),
					"description" => count((array)($group["callouts"] ?? [])) . " callout(s)",
				];
			}, $groups);

			$options[] = [
				"id" => "",
				"label" => "No group",
				"description" => "Create the callout ungrouped (group-restricted page regions won't offer it)",
			];

			return [
				"needs_input" => [
					"question" => "There's no callout group called “{$requested}”. Which group should this callout go in? "
						. "(create_callout_group can make a new one.)",
					"options" => $options,
				],
				// "Create a Promos group and put this callout in it" stages the group,
				// then arrives here with nothing written — and the question above would
				// offer the model the groups that already exist, which is the opposite
				// of what was asked. When the group is already on a card, say so
				// instead (audit #9 A1).
				"prior_change" => [
					"tool" => "create_callout_group",
					"value" => $requested,
					"keys" => ["name"],
					"label" => "A callout group called “{$requested}”",
				],
			];
		}

		/**
		 * Validate creating a callout group. Developer-only, like every callout write.
		 *
		 * Exists to unblock create_callout's group question: without it the only
		 * answers to "which group?" are the ones that already exist, which is a
		 * dead end the moment the answer is "a new one".
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateCalloutGroupCreate(array $args, $user): array {
			if (PermissionService::level($user) < 2) {

				return ["denied" => "Only developers can create callout groups."];
			}

			$name = trim((string)($args["name"] ?? ""));

			if ($name === "") {

				return ["error" => "A callout group name is required (e.g. \"Sidebar\")."];
			}

			if (mb_strlen($name) > self::AI_GROUP_NAME_MAX_LENGTH) {

				return ["error" => "The callout group name is " . mb_strlen($name) . " characters, but the field holds "
					. "at most " . self::AI_GROUP_NAME_MAX_LENGTH . ". Shorten it and try again."];
			}

			foreach (BigTreeJSONDB::getAll("callout-groups") as $group) {
				if (strcasecmp((string)($group["name"] ?? ""), $name) === 0) {

					return ["error" => "A callout group called “{$name}” already exists."];
				}
			}

			$members = $this->aiResolveGroupCallouts($args["callouts"] ?? []);

			if (isset($members["error"])) {

				return $members;
			}

			$preview = [
				"action" => "create_callout_group",
				"name" => $name,
				"callouts" => $members["labels"] ? implode(", ", $members["labels"]) : "(none — starts empty)",
			];

			// A callout belongs to one group at a time (aiRemoveCalloutFromGroups keeps
			// it exclusive), so filling a group silently empties part of another one.
			// Name what moves rather than letting the developer discover it.
			if ($members["moves"]) {
				$preview["moves_out_of"] = implode("; ", $members["moves"]);
			}

			$summary = "Create a new callout group “{$name}”.";

			if ($members["labels"]) {
				$summary .= " " . count($members["labels"]) . " callout"
					. (count($members["labels"]) === 1 ? "" : "s") . " will be put in it: "
					. implode(", ", $members["labels"]) . ".";

				if ($members["moves"]) {
					$summary .= " " . implode("; ", $members["moves"]) . ".";
				}
			} else {
				$summary .= " It starts empty — callouts are added to it as they're created.";
			}

			return [
				"ok" => true,
				"summary" => $summary,
				"preview" => $preview,
				"payload" => ["name" => $name, "callouts" => $members["ids"]],
			];
		}

		/**
		 * Resolve the callouts a new group should contain, by id or name.
		 *
		 * Membership was settable only from the callout's side (create_callout /
		 * update_callout take a `group`), so "put these four callouts in a new Promos
		 * group" cost five proposals — and the four edits couldn't even be staged until
		 * the group's own card was approved, because a staged group has no id. The
		 * route has accepted `callouts` on POST /callout-groups all along.
		 *
		 * @param mixed $requested Callout ids or names.
		 * @return array{ids:list<string>,labels:list<string>,moves:list<string>}|array<string,mixed>
		 */
		private function aiResolveGroupCallouts($requested): array {
			$empty = ["ids" => [], "labels" => [], "moves" => []];

			if ($requested === null || $requested === "") {

				return $empty;
			}

			if (!is_array($requested)) {

				return ["error" => "`callouts` must be a list of callout ids or names, e.g. [\"promo\", \"Sidebar "
					. "Promo\"]."];
			}

			$callouts = BigTreeJSONDB::getAll("callouts");
			$ids = [];
			$labels = [];
			$moves = [];

			foreach ($requested as $wanted) {
				$wanted = trim((string)$wanted);

				if ($wanted === "") {

					continue;
				}

				$match = null;

				foreach ($callouts as $callout) {
					$id = (string)($callout["id"] ?? "");

					if ($id === $wanted || strcasecmp((string)($callout["name"] ?? ""), $wanted) === 0) {
						$match = $callout;

						break;
					}
				}

				if ($match === null) {

					return [
						"error" => "There's no callout “{$wanted}”. Available callouts: "
							. $this->aiDescribeCallouts($callouts) . ".",
						// It may be staged rather than absent: "create a promo callout
						// and a Promos group with it in" is the same two-step intent
						// from the other direction (audit #9 A1).
						"prior_change" => [
							"tool" => "create_callout",
							"value" => $wanted,
							"keys" => ["id", "name"],
							"label" => "A callout called “{$wanted}”",
						],
					];
				}

				$id = (string)$match["id"];

				if (in_array($id, $ids, true)) {

					continue;
				}

				$ids[] = $id;
				$labels[] = (string)($match["name"] ?? "") !== "" ? "{$match["name"]} ({$id})" : $id;

				foreach ($this->aiCalloutGroupsFor($id) as $group) {
					$moves[] = "“{$labels[count($labels) - 1]}” moves out of the “{$group["name"]}” group";
				}
			}

			return ["ids" => $ids, "labels" => $labels, "moves" => $moves];
		}

		/**
		 * A short "Name (id)" list of the callouts that exist, for an error message.
		 *
		 * @param list<array<string,mixed>> $callouts
		 */
		private function aiDescribeCallouts(array $callouts): string {
			$parts = [];

			foreach ($callouts as $callout) {
				$id = (string)($callout["id"] ?? "");
				$name = (string)($callout["name"] ?? "");
				$parts[] = $name !== "" ? "{$name} ({$id})" : $id;
			}

			return $parts ? implode(", ", $parts) : "(none)";
		}

		/**
		 * Execute an approved callout group creation. Re-checks developer level and
		 * that the name is still free.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiCreateCalloutGroup(array $payload, $user): array {
			if (PermissionService::level($user) < 2) {
				throw new AuthorizationException("Only developers can create callout groups.");
			}

			$name = trim((string)($payload["name"] ?? ""));

			if ($name === "" || mb_strlen($name) > self::AI_GROUP_NAME_MAX_LENGTH) {

				return ["mode" => "error", "message" => "That callout group can no longer be created."];
			}

			foreach (BigTreeJSONDB::getAll("callout-groups") as $group) {
				if (strcasecmp((string)($group["name"] ?? ""), $name) === 0) {

					return ["mode" => "error", "message" => "A callout group called “{$name}” already exists."];
				}
			}

			// Re-resolved at approval like every other staged reference: a callout can be
			// deleted inside the proposal's 24h life, and a dead id in a group's list is
			// a member the group renders as nothing. Missing ones are dropped with a
			// note rather than refusing the whole group — the same degrade
			// aiCreateCallout applies to a vanished group.
			$staged = is_array($payload["callouts"] ?? null) ? $payload["callouts"] : [];
			$callouts = [];
			$missing = [];

			foreach ($staged as $callout_id) {
				$callout_id = (string)$callout_id;

				if ($callout_id === "" || in_array($callout_id, $callouts, true)) {

					continue;
				}

				if (BigTreeJSONDB::exists("callouts", $callout_id)) {
					$callouts[] = $callout_id;
				} else {
					$missing[] = $callout_id;
				}
			}

			$id = BigTreeJSONDB::insert("callout-groups", [
				"name" => BigTree::safeEncode($name),
				"callouts" => $callouts,
			]);

			// Membership is exclusive, so a callout joining this group leaves whichever
			// one it was in. Done after the insert because the new group's id is what
			// the removal has to preserve.
			foreach ($callouts as $callout_id) {
				$this->aiRemoveCalloutFromGroups($callout_id, (string)$id);
			}

			return [
				"mode" => "created",
				"id" => (string)$id,
				"name" => $name,
				"callouts" => $callouts,
				"note" => $missing
					? "These callouts no longer exist and were left out of the group: " . implode(", ", $missing) . "."
					: null,
			];
		}

		/**
		 * Append a newly created callout to its group's `callouts` list.
		 *
		 * Re-resolved at approval: the group can be renamed or deleted during the
		 * proposal's TTL, and a stale id would otherwise write a dangling membership.
		 */
		private function aiAddCalloutToGroup(string $group_id, string $callout_id): bool {
			if ($group_id === "" || !BigTreeJSONDB::exists("callout-groups", $group_id)) {

				return false;
			}

			$group = BigTreeJSONDB::get("callout-groups", $group_id);
			$callouts = array_values(array_filter((array)($group["callouts"] ?? []), "is_string"));

			if (in_array($callout_id, $callouts, true)) {

				return true;
			}

			$callouts[] = $callout_id;
			$group["callouts"] = $callouts;
			BigTreeJSONDB::update("callout-groups", $group_id, $group);

			return true;
		}

		/**
		 * Normalize the model's proposed callout fields into the canonical resource
		 * shape, merged against what the callout already stores so a field the model
		 * copied over unchanged keeps its settings.
		 *
		 * @param mixed $fields
		 * @param list<array<string,mixed>> $existing Stored resources ([] when creating).
		 * @return list<array<string,mixed>>
		 */
		private function aiCalloutFields($fields, array $existing = []): array {

			return Resources::mergeAiFields($fields, $existing, "callout");
		}

		/**
		 * Whether a callout resource carries the legacy `required` validation rule —
		 * the same rule string the template resources and the page editor use.
		 *
		 * @param array<string,mixed> $resource
		 */
		private function aiResourceIsRequired(array $resource): bool {
			$settings = is_array($resource["settings"] ?? null) ? $resource["settings"] : [];
			$validation = (string)($settings["validation"] ?? "");

			return in_array("required", preg_split("/\s+/", trim($validation), -1, PREG_SPLIT_NO_EMPTY) ?: [], true);
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
