<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Flag;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\AI\Tools\SettingToolBackend;
	use BigTreeCMS;
	use BigTreeJSONDB;
	use BigTree;
	use SQL;

	/**
	 * Settings live in two places:
	 *  - definition in JSONDB ("settings")
	 *  - value in bigtree_settings (longblob, optionally AES-encrypted)
	 *
	 * v1 endpoint conventions:
	 *  - list/get return the decoded value unless the setting is encrypted AND the
	 *    caller did not pass ?include_encrypted=1 with level >= 2.
	 *  - level:1 can update VALUES of any setting; level:2 can change DEFINITIONS.
	 *  - bigtree-internal-* settings are hidden from list and not writable via this API.
	 */
	class SettingService implements SettingToolBackend {
		public function list(Request $request) {
			$q = $request->queryString("q");
			$include_encrypted = !empty($request->query["include_encrypted"]) && (int)$request->user->level >= 2;
			$include_system = $request->queryBool("include_system");

			$defs = BigTreeJSONDB::getAll("settings");
			$filtered = [];

			foreach ($defs as $def) {
				if (strpos($def["id"] ?? "", "bigtree-internal-") === 0) {
					continue;
				}

				if (!$include_system && !empty($def["system"])) {
					continue;
				}

				if ($q !== "") {
					$hay = strtolower(($def["id"] ?? "") . " " . ($def["name"] ?? "") . " " . ($def["description"] ?? ""));

					if (strpos($hay, strtolower($q)) === false) {
						continue;
					}
				}

				$filtered[] = $def;
			}

			usort($filtered, function ($a, $b) { return strcasecmp($a["name"] ?? $a["id"], $b["name"] ?? $b["id"]); });

			return Pagination::paginateRows($request, $filtered, function ($d) use ($include_encrypted) {

				return $this->present($d, $include_encrypted);
			}, 100);
		}

		public function get(Request $request) {
			$id = $request->routeParam("id");

			if (strpos($id, "bigtree-internal-") === 0) {
				throw new AuthorizationException("Internal settings are not exposed via this API");
			}

			$include_encrypted = !empty($request->query["include_encrypted"]) && (int)$request->user->level >= 2;
			$def = Entity::findOrFailJson("settings", $id, "Setting");

			return Response::ok($this->present($def, $include_encrypted));
		}

		public function create(Request $request) {
			$d = $request->body;
			$id = (string)$d["id"];

			if (strpos($id, "bigtree-internal-") === 0) {
				throw new BadRequestException("Cannot create bigtree-internal- settings via API", "reserved_id");
			}

			if (SQL::exists("bigtree_settings", $id) || BigTreeJSONDB::exists("settings", $id)) {
				throw new ConflictException("Setting $id already exists", "duplicate_id");
			}

			$encrypted = Flag::checkbox($d["encrypted"] ?? null);
			BigTreeJSONDB::insert("settings", [
				"id" => $id,
				"name" => BigTree::safeEncode($d["name"] ?? $id),
				"description" => $d["description"] ?? "",
				"type" => $d["type"] ?? "text",
				"settings" => is_array($d["settings"] ?? null) ? $d["settings"] : [],
				"locked" => Flag::checkbox($d["locked"] ?? null),
				"system" => Flag::checkbox($d["system"] ?? null),
				"encrypted" => $encrypted,
				"extension" => $d["extension"] ?? null,
			]);
			SQL::insert("bigtree_settings", ["id" => $id, "encrypted" => $encrypted, "value" => ""]);

			$def = BigTreeJSONDB::get("settings", $id);

			return Response::created($this->present($def, true), null);
		}

		public function update(Request $request) {
			$id = $request->routeParam("id");

			if (strpos($id, "bigtree-internal-") === 0) {
				throw new AuthorizationException("Internal settings cannot be modified via API");
			}

			$def = Entity::findOrFailJson("settings", $id, "Setting");

			$d = $request->body;
			$value_only = isset($d["value"]) && count(array_diff(array_keys($d), ["value"])) === 0;

			if ($value_only) {
				if ((int)$request->user->level < 1) {
					throw new AuthorizationException("Updating a value requires level:1");
				}

				// Mirror legacy settings/update.php: system settings are never
				// value-editable via the admin; locked settings need a developer.
				if (!empty($def["system"])) {
					throw new AuthorizationException("System settings cannot be modified", "system_setting");
				}

				if (!empty($def["locked"]) && (int)$request->user->level < 2) {
					throw new AuthorizationException("Locked settings require developer access", "locked_setting");
				}

				$this->setValue($id, $def, $d["value"]);
			} else {
				if ((int)$request->user->level < 2) {
					throw new AuthorizationException("Updating a setting definition requires level:2");
				}

				$this->updateDefinition($id, $def, $d);

				if (array_key_exists("value", $d)) {
					$this->setValue($d["id"] ?? $id, BigTreeJSONDB::get("settings", $d["id"] ?? $id), $d["value"]);
				}
			}

			$fresh = BigTreeJSONDB::get("settings", $d["id"] ?? $id);

			return Response::ok($this->present($fresh, true));
		}

		public function delete(Request $request) {
			$id = $request->routeParam("id");

			if (strpos($id, "bigtree-internal-") === 0) {
				throw new AuthorizationException("Internal settings cannot be deleted via API");
			}

			Entity::assertExistsJson("settings", $id, "Setting");

			BigTreeJSONDB::delete("settings", $id);
			SQL::delete("bigtree_settings", $id);
			ResourceAllocationService::deallocateResources("bigtree_settings", $id);
			EmbeddingService::deleteSetting((string)$id);

			return Response::noContent();
		}

		// — internals —

		private function setValue($id, array $def, $value) {
			global $bigtree;
			$json = json_encode($value);

			// A setting definition can exist with no value row — the definition is
			// JSON-DB, the value is SQL, and only create() writes both. Without this
			// the UPDATE matched nothing, the value was never stored, and the caller
			// (including an AI approval) was told "updated".
			if (!SQL::exists("bigtree_settings", $id)) {
				SQL::insert("bigtree_settings", [
					"id" => $id,
					"encrypted" => !empty($def["encrypted"]) ? "on" : "",
					"value" => "",
				]);
			}

			if (!empty($def["encrypted"])) {
				$key = $bigtree["config"]["settings_key"] ?? "";
				SQL::query("UPDATE bigtree_settings SET value = AES_ENCRYPT(?, ?) WHERE id = ?", $json, $key, $id);
			} else {
				SQL::update("bigtree_settings", $id, ["value" => $json]);
			}

			// Track resource allocations from the plaintext value. Reference-type
			// settings store a bare resource id under "value"; other types embed
			// irl:// / resource:// / file URLs that scan without reference keys.
			$reference_keys = in_array(
				$def["type"] ?? "",
				["image-reference", "file-reference", "video-reference"],
				true
			) ? ["value"] : null;

			ResourceAllocationService::allocateResourcesFromData("bigtree_settings", $id, ["value" => $value], $reference_keys);
			EmbeddingService::deferIndex(function () use ($id, $value) {
				EmbeddingService::indexSetting((string)$id, $value);
			});
		}

		private function updateDefinition($old_id, array $existing, array $d) {
			global $bigtree;
			$new_id = $d["id"] ?? $old_id;

			if ($new_id !== $old_id && BigTreeJSONDB::exists("settings", $new_id)) {
				throw new ConflictException("Setting $new_id already exists", "duplicate_id");
			}

			$next = [
				"id" => $new_id,
				"name" => BigTree::safeEncode($d["name"] ?? $existing["name"]),
				"description" => $d["description"] ?? ($existing["description"] ?? ""),
				"type" => $d["type"] ?? ($existing["type"] ?? "text"),
				"settings" => is_array($d["settings"] ?? null) ? $d["settings"] : ($existing["settings"] ?? []),
				"locked" => Flag::checkbox($d["locked"] ?? null),
				"system" => Flag::checkbox($d["system"] ?? null),
				"encrypted" => Flag::checkbox($d["encrypted"] ?? null),
				"extension" => $d["extension"] ?? ($existing["extension"] ?? null),
			];
			BigTreeJSONDB::update("settings", $old_id, $next);

			if ($new_id !== $old_id) {
				SQL::update("bigtree_settings", $old_id, ["id" => $new_id]);
			}

			$key = $bigtree["config"]["settings_key"] ?? "";

			if (!empty($existing["encrypted"]) && empty($d["encrypted"])) {
				SQL::query("UPDATE bigtree_settings SET value = AES_DECRYPT(value, ?), encrypted = '' WHERE id = ?", $key, $new_id);
			} elseif (empty($existing["encrypted"]) && !empty($d["encrypted"])) {
				SQL::query("UPDATE bigtree_settings SET value = AES_ENCRYPT(value, ?), encrypted = 'on' WHERE id = ?", $key, $new_id);
			}
		}

		private function present(array $def, $include_encrypted_value) {
			global $bigtree;
			$out = [
				"id" => $def["id"],
				"name" => $def["name"] ?? $def["id"],
				"description" => $def["description"] ?? "",
				"type" => $def["type"] ?? "text",
				"settings" => $def["settings"] ?? [],
				"locked" => !empty($def["locked"]),
				"system" => !empty($def["system"]),
				"encrypted" => !empty($def["encrypted"]),
				"extension" => $def["extension"] ?? null,
			];

			if (empty($def["encrypted"])) {
				$raw = SQL::fetchSingle("SELECT value FROM bigtree_settings WHERE id = ?", $def["id"]);
				$out["value"] = is_string($raw) ? json_decode($raw, true) : null;
			} elseif ($include_encrypted_value) {
				$key = $bigtree["config"]["settings_key"] ?? "";
				$raw = SQL::fetchSingle("SELECT AES_DECRYPT(value, ?) FROM bigtree_settings WHERE id = ?", $key, $def["id"]);
				$out["value"] = is_string($raw) ? json_decode($raw, true) : null;
			} else {
				$out["value"] = null;
				$out["value_omitted"] = true;
			}

			return $out;
		}
	
		public static function readSetting($id, $decode = true) {
			global $bigtree;

			$id = BigTreeCMS::extensionSettingCheck($id);
			$setting = BigTreeJSONDB::get("settings", $id);

			// Internal (bigtree-internal-*) settings and any value written straight
			// to bigtree_settings have no JSONDB definition. Fall back to the SQL
			// row so those settings still resolve (matching the legacy
			// BigTreeCMS::getSetting behavior, which read purely from SQL).
			if (!$setting) {
				$setting = SQL::fetch("SELECT * FROM bigtree_settings WHERE id = ?", $id);

				if (!$setting) {
					return false;
				}
			}

			if ($setting["encrypted"]) {
				$setting["value"] = SQL::fetchSingle("SELECT AES_DECRYPT(`value`, ?) FROM bigtree_settings WHERE id = ?", $bigtree["config"]["settings_key"], $id);
			} else {
				$setting["value"] = SQL::fetchSingle("SELECT value FROM bigtree_settings WHERE id = ?", $id);
			}

			// Decode the JSON value
			if ($decode) {
				$setting["value"] = json_decode($setting["value"] ?? "", true);

				if (is_array($setting["value"])) {
					$setting["value"] = BigTree::untranslateArray($setting["value"]);
				} else {
					$setting["value"] = BigTreeCMS::replaceInternalPageLinks($setting["value"]);
				}
			}

			return $setting;
		}

		public static function updateInternalValue($id, $value, $encrypted = false) {
			global $bigtree;

			if (is_array($value)) {
				$value = BigTree::translateArray($value);
			} else {
				$value = LinkService::autoIPL($value);
			}

			$value = BigTree::json($value);

			if (!SQL::exists("bigtree_settings", $id)) {
				SQL::insert("bigtree_settings", [
					"id" => $id,
					"encrypted" => $encrypted ? "on" : ""
				]);
			}

			if ($encrypted) {
				SQL::query("UPDATE bigtree_settings SET `value` = AES_ENCRYPT(?, ?), `encrypted` = 'on' WHERE id = ?", $value, $bigtree["config"]["settings_key"], $id);
			} else {
				SQL::update("bigtree_settings", $id, ["value" => $value, "encrypted" => ""]);
			}
		}

		/**
		 * Per-request page size from bigtree-internal-per-page (default 15).
		 * Replaces silent reliance on a static that was only populated after the
		 * admin constructor ran.
		 */
		public static function perPage(): int {
			static $cached = null;

			if ($cached !== null) {
				return $cached;
			}

			$setting = static::readSetting("bigtree-internal-per-page");
			$v = is_array($setting) ? ($setting["value"] ?? 15) : 15;
			$cached = max(1, (int) $v);

			return $cached;
		}

		// — AI tool seam (SettingToolBackend) —
		//
		// The assistant reads and writes setting VALUES only (never definitions), for
		// administrators (level ≥ 1), and refuses internal/encrypted/locked settings —
		// the same guardrails the REST routes enforce, packaged without a Request. The
		// write reuses setValue() so allocation + embedding indexing come for free.

		/**
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetSettings(string $query, int $limit, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can read settings."];
			}

			$query = strtolower(trim($query));
			$limit = max(1, $limit);
			$defs = BigTreeJSONDB::getAll("settings");
			$out = [];
			$more = false;

			foreach ($defs as $def) {
				if (strpos($def["id"] ?? "", "bigtree-internal-") === 0) {

					continue;
				}

				// list() hides system settings unless ?include_system=1 is asked for;
				// the read tool has no such argument, so it takes list()'s default and
				// doesn't hand the model the plaintext value of every service endpoint
				// and feature switch an extension registered.
				if (!empty($def["system"])) {

					continue;
				}

				if ($query !== "") {
					$hay = strtolower(($def["id"] ?? "") . " " . ($def["name"] ?? "") . " " . ($def["description"] ?? ""));

					if (strpos($hay, $query) === false) {

						continue;
					}
				}

				// One qualifying setting past the window means the scan was truncated —
				// so "is there a setting for X?" can't be answered "no" from a partial
				// list (audit #7 D4).
				if (count($out) >= $limit) {
					$more = true;

					break;
				}

				// Encrypted values are never surfaced to the model.
				$out[] = $this->present($def, false);
			}

			return ["settings" => $out, "has_more" => $more];
		}

		/**
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateSettingUpdate(array $args, $user): array {
			if (PermissionService::level($user) < 1) {

				return ["denied" => "Only administrators can change settings."];
			}

			$id = trim((string)($args["id"] ?? ""));

			if ($id === "") {

				return ["error" => "A setting id is required."];
			}

			if (strpos($id, "bigtree-internal-") === 0) {

				return ["denied" => "Internal BigTree settings cannot be changed via the assistant."];
			}

			$def = BigTreeJSONDB::get("settings", $id);

			if (!$def) {

				return ["error" => "Setting \"{$id}\" does not exist."];
			}

			if (!empty($def["encrypted"])) {

				return ["denied" => "That setting is encrypted and cannot be changed via the assistant."];
			}

			if (!empty($def["locked"])) {

				return ["denied" => "That setting is locked and cannot be changed via the assistant."];
			}

			// REST refuses outright on the value-only branch this tool is the analogue
			// of (see update()'s "System settings cannot be modified"), and the admin UI
			// won't offer the field either — so the assistant must not be the one
			// surface that writes it.
			if (!empty($def["system"])) {

				return ["denied" => "That is a system setting and cannot be changed via the assistant. Change it in "
					. "Developer → Settings."];
			}

			if (!array_key_exists("value", $args)) {

				return ["error" => "A new value is required."];
			}

			$name = (string)($def["name"] ?? $id);

			// Nothing used to check the value against the setting's own field type, so
			// a select could be set outside its options, a structured setting handed a
			// scalar, and an image setting given a fabricated path. The REST route is
			// equally loose, but a model guessing value shapes makes malformed writes
			// far more likely — and a front-end template that foreachs over a setting
			// fatals on a scalar.
			$checked = $this->aiCheckSettingValue($def, $args["value"]);

			if (isset($checked["error"])) {

				return $checked;
			}

			$value = $checked["value"];
			$current_raw = SQL::fetchSingle("SELECT value FROM bigtree_settings WHERE id = ?", $id);
			$current = is_string($current_raw) ? json_decode($current_raw, true) : null;

			return [
				"ok" => true,
				"summary" => "Change the value of the “{$name}” setting.",
				"preview" => [
					"action" => "update_setting",
					"id" => $id,
					"name" => $name,
					"type" => (string)($def["type"] ?? "text"),
					"from" => $this->aiSettingPreviewValue($current),
					"to" => $this->aiSettingPreviewValue($value),
				],
				"payload" => [
					"id" => $id,
					"value" => $value,
				],
				// The definition, not the value: a setting retyped or flagged system
				// inside the TTL must not take a value staged against the old shape.
				// The stored *value*, not the definition. Hashing the definition meant
				// the check only noticed a developer retyping the setting — a
				// concurrent value edit was clobbered silently, and settings have
				// neither a pending queue nor history to recover it from.
				"fingerprint" => [
					"type" => "composite",
					"parts" => [
						["type" => "setting_value", "id" => $id],
						["type" => "json_record", "store" => "settings", "id" => $id],
					],
				],
				// The pair SettingEdit's useLock call passes.
				"lock" => ["table" => "config:settings", "id" => $id],
			];
		}

		// Setting field types the assistant must never author: they reference real
		// uploaded files or carry structure a model would be guessing at. Mirrors the
		// simple-scalar allowlists in PageService / AutoModuleService.
		private const AI_UNSETTABLE_SETTING_TYPES = [
			"upload", "image", "image-reference", "file-reference", "media-gallery",
			"video", "video-reference", "matrix", "callouts", "one-to-many",
			"many-to-many", "geocoding", "route",
		];

		// Types whose stored value is a plain scalar — an array is always wrong.
		private const AI_SCALAR_SETTING_TYPES = [
			"text", "textarea", "html", "htmleditor", "simple-editor", "code",
			"number", "currency", "phone", "email", "color",
			"date", "datetime", "time", "checkbox", "list", "link",
		];

		/**
		 * Validate (and where appropriate coerce) a proposed setting value against the
		 * setting definition's field type.
		 *
		 * Returns ["value" => mixed] with the value to store, or ["error" => string]
		 * with a recoverable message the model can act on.
		 *
		 * @param array<string,mixed> $def
		 * @param mixed $value
		 * @return array<string,mixed>
		 */
		private function aiCheckSettingValue(array $def, $value): array {
			$type = (string)($def["type"] ?? "text");
			$name = (string)($def["name"] ?? $def["id"] ?? "this setting");

			if (in_array($type, self::AI_UNSETTABLE_SETTING_TYPES, true)) {

				return ["error" => "“{$name}” is a {$type} setting — the assistant can't author that kind of value. "
					. "Change it on the Settings screen in the admin."];
			}

			$rule_error = $this->aiSettingRuleViolation($def, $name, $value);

			if ($rule_error !== null) {

				return ["error" => $rule_error];
			}

			if (in_array($type, self::AI_SCALAR_SETTING_TYPES, true) && (is_array($value) || is_object($value))) {

				return ["error" => "“{$name}” is a {$type} setting and expects a single value, not a list or object."];
			}

			if ($type === "checkbox") {

				return ["value" => (!empty($value) && $value !== "false") ? "on" : ""];
			}

			if ($type === "number" || $type === "currency") {
				if (!is_numeric($value)) {

					return ["error" => "“{$name}” is a {$type} setting and expects a number — got \""
						. $this->aiSettingPreviewValue($value) . "\"."];
				}

				return ["value" => $value + 0];
			}

			if ($type === "list") {

				return $this->aiCheckListSettingValue($def, $name, $value);
			}

			// Scalar-but-shaped types. These fell through to "store as given", so
			// "next Tuesday" landed verbatim in a date setting — the page-schedule path
			// rejects exactly that with a helpful message, and so should this.
			$date_formats = ["date" => "Y-m-d", "datetime" => "Y-m-d H:i:s", "time" => "H:i:s"];

			if (isset($date_formats[$type])) {
				$raw = trim((string)$value);

				if ($raw === "") {

					return ["value" => ""];
				}

				$stamp = strtotime($raw);

				if ($stamp === false) {

					return ["error" => "“{$name}” is a {$type} setting and \"{$raw}\" isn't a {$type} I can store. "
						. "Use an explicit value like \"" . date($date_formats[$type]) . "\"."];
				}

				return ["value" => date($date_formats[$type], $stamp)];
			}

			if ($type === "email") {
				$raw = trim((string)$value);

				if ($raw !== "" && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {

					return ["error" => "“{$name}” is an email setting and \"{$raw}\" isn't a valid email address."];
				}

				return ["value" => $raw];
			}

			// Anything left is a scalar type with no enumerable domain (text, html…)
			// or a type this build doesn't know about — store as given. Phone and
			// colour are deliberately not policed: their formats are conventions
			// rather than rules, and a false rejection is worse than a loose value.
			return ["value" => $value];
		}

		/**
		 * Check a proposed value against the setting definition's own `validation`
		 * rule string, the way SettingEdit does before it saves and the way every
		 * other AI write path does (AutoModuleService::aiRuleViolation).
		 *
		 * A setting definition carries the same whitespace-separated rule list a form
		 * field does — "required", "numeric", "link" — and nothing on this path read
		 * it, so the assistant could blank a required setting or write "about a
		 * hundred" into a numeric one and have it stored.
		 *
		 * @param array<string,mixed> $def
		 * @param mixed $value
		 * @return string|null An error, or null when the value passes.
		 */
		private function aiSettingRuleViolation(array $def, string $name, $value): ?string {
			$settings = is_array($def["settings"] ?? null) ? $def["settings"] : [];
			$validation = trim((string)($settings["validation"] ?? ""));
			$rules = $validation !== "" ? preg_split("/\s+/", $validation, -1, PREG_SPLIT_NO_EMPTY) : [];
			$rules = $rules ?: [];
			$required = !empty($settings["required"]) || in_array("required", $rules, true);
			$empty = is_array($value) ? $value === [] : trim((string)$value) === "";

			if ($required && $empty) {

				return "“{$name}” is a required setting, so it can't be cleared. Supply a value, or change the "
					. "requirement in Developer → Settings.";
			}

			$rules = array_values(array_diff($rules, ["required"]));

			// An empty optional value has nothing left to validate — only `required`
			// has anything to say about emptiness.
			if (!$rules || $empty || is_array($value)) {

				return null;
			}

			$rule_string = implode(" ", $rules);

			if (\BigTreeAutoModule::validate($value, $rule_string)) {

				return null;
			}

			// The legacy message opens "This field …"; name the setting instead.
			$reason = preg_replace(
				"/^This field /",
				"",
				\BigTreeAutoModule::validationErrorMessage($value, $rule_string)
			);

			return "“{$name}” " . $reason . " \"" . $this->aiSettingPreviewValue($value) . "\" would be refused.";
		}

		/**
		 * Enumerated ("list") settings: the value must be one of the field's options.
		 *
		 * Only static and database-populated lists have a domain we can resolve here;
		 * state/country lists are left to the field type's own rendering, matching how
		 * loosely the admin treats them.
		 *
		 * @param array<string,mixed> $def
		 * @param mixed $value
		 * @return array<string,mixed>
		 */
		private function aiCheckListSettingValue(array $def, string $name, $value): array {
			$settings = is_array($def["settings"] ?? null) ? $def["settings"] : [];
			$list_type = (string)($settings["list_type"] ?? "static");
			$options = [];

			if ($list_type === "static") {
				foreach ((array)($settings["list"] ?? []) as $option) {
					if (is_array($option) && array_key_exists("value", $option)) {
						$options[] = (string)$option["value"];
					}
				}
			} elseif ($list_type === "db") {
				$table = (string)($settings["pop-table"] ?? "");

				if ($table === "" || !SQL::tableExists($table)) {

					return ["value" => $value];
				}

				foreach (SQL::fetchAllSingle("SELECT id FROM `".str_replace("`", "", $table)."`") as $row_id) {
					$options[] = (string)$row_id;
				}
			} else {
				// state / country / unknown — no locally resolvable domain.
				return ["value" => $value];
			}

			if (!$options) {

				return ["value" => $value];
			}

			$allow_empty = (string)($settings["allow-empty"] ?? "") !== "No";

			if ($allow_empty && (string)$value === "") {

				return ["value" => ""];
			}

			if (!in_array((string)$value, $options, true)) {
				$shown = array_slice($options, 0, 20);
				$suffix = count($options) > count($shown) ? ", …" : "";

				return ["error" => "\"" . $this->aiSettingPreviewValue($value) . "\" is not a valid option for “{$name}”. "
					. "Valid options: " . implode(", ", $shown) . $suffix . "."];
			}

			return ["value" => (string)$value];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiUpdateSetting(array $payload, $user): array {
			if (PermissionService::level($user) < 1) {
				throw new AuthorizationException("Only administrators can change settings.");
			}

			$id = (string)($payload["id"] ?? "");
			$def = $id !== "" ? BigTreeJSONDB::get("settings", $id) : null;

			if (!$def || strpos($id, "bigtree-internal-") === 0) {

				return ["mode" => "error", "message" => "That setting is no longer available."];
			}

			// A definition can gain any of these flags inside the proposal's 24h TTL,
			// so they are re-asked here rather than trusted from staging.
			if (!empty($def["encrypted"]) || !empty($def["locked"]) || !empty($def["system"])) {

				return ["mode" => "error", "message" => "That setting can no longer be changed."];
			}

			// Re-check at approval: a definition can change between staging and
			// approval (options edited, type switched), and the same guard that made
			// the value safe to propose is what makes it safe to write.
			$checked = $this->aiCheckSettingValue($def, $payload["value"] ?? null);

			if (isset($checked["error"])) {

				return ["mode" => "error", "message" => $checked["error"]];
			}

			$this->setValue($id, $def, $checked["value"]);

			return [
				"mode" => "updated",
				"id" => $id,
				"name" => (string)($def["name"] ?? $id),
			];
		}

		/**
		 * A short, model/SPA-safe rendering of a setting value for a proposal preview:
		 * scalars pass through, structured values are compacted and length-capped.
		 *
		 * @param mixed $value
		 */
		private function aiSettingPreviewValue($value): string {
			if ($value === null) {

				return "";
			}

			if (is_scalar($value)) {
				$string = (string)$value;
			} else {
				$string = (string)json_encode($value);
			}

			if (mb_strlen($string) > 200) {
				$string = mb_substr($string, 0, 199) . "…";
			}

			return $string;
		}
	}
