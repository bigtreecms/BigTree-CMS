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
			$defs = BigTreeJSONDB::getAll("settings");
			$out = [];

			foreach ($defs as $def) {
				if (strpos($def["id"] ?? "", "bigtree-internal-") === 0) {

					continue;
				}

				if ($query !== "") {
					$hay = strtolower(($def["id"] ?? "") . " " . ($def["name"] ?? "") . " " . ($def["description"] ?? ""));

					if (strpos($hay, $query) === false) {

						continue;
					}
				}

				// Encrypted values are never surfaced to the model.
				$out[] = $this->present($def, false);

				if (count($out) >= max(1, $limit)) {

					break;
				}
			}

			return ["settings" => $out];
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

			if (!array_key_exists("value", $args)) {

				return ["error" => "A new value is required."];
			}

			$name = (string)($def["name"] ?? $id);
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
					"to" => $this->aiSettingPreviewValue($args["value"]),
				],
				"payload" => [
					"id" => $id,
					"value" => $args["value"],
				],
			];
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

			if (!empty($def["encrypted"]) || !empty($def["locked"])) {

				return ["mode" => "error", "message" => "That setting can no longer be changed."];
			}

			$this->setValue($id, $def, $payload["value"] ?? null);

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
