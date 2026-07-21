<?php
	namespace BigTree\Api;

	use BigTree;
	use BigTreeCMS;

	/**
	 * Shared cleaning for the resources array carried by callouts and templates
	 * (and conceptually by modules). Normalizes an incoming resources list into
	 * the canonical {id, type, title, subtitle, settings} shape, safe-encoding the
	 * scalar fields and recursively filtering the settings. The canonical home for
	 * future resource-field cleaning changes.
	 */
	class Resources {
		/**
		 * Normalize a resources array. Entries without an id are dropped; "settings"
		 * falls back to the legacy "options" key and is JSON-decoded when supplied as
		 * a string. Accepts mixed input (cast to array) to tolerate malformed request
		 * bodies rather than fataling.
		 *
		 * @param mixed $resources Raw resources value from a request body.
		 * @return array The cleaned, canonically-shaped resources list.
		 */
		public static function clean($resources): array {
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

		/**
		 * Merge an AI-proposed field list into a stored resources list, preserving
		 * everything the assistant's field shape cannot carry.
		 *
		 * The assistant describes a field as {id, type, title, subtitle} — it has no
		 * way to express validation rules, list options, image presets or matrix
		 * subfields. Because supplying `fields` replaces the *whole* resource list,
		 * rebuilding each field from that shape stripped `settings` from every field
		 * the model merely copied over unchanged, including the `required` rules the
		 * page and entry gates depend on. So a retained field now keeps its stored
		 * record and only takes the keys the proposal actually supplied; a genuinely
		 * new field starts bare apart from an optional `required`.
		 *
		 * Retyping a field drops its settings deliberately — they are configuration
		 * for the *old* field type — and the caller's field diff discloses the retype.
		 *
		 * @param mixed $fields Raw AI-proposed field list.
		 * @param array $existing Stored resources to merge against ([] when creating).
		 * @return array The cleaned, canonically-shaped resources list.
		 */
		public static function mergeAiFields($fields, array $existing = []): array {
			$stored = [];

			foreach ($existing as $resource) {
				$id = is_array($resource) ? (string)($resource["id"] ?? "") : "";

				if ($id !== "") {
					$stored[$id] = $resource;
				}
			}

			$rows = [];

			foreach ((array)$fields as $field) {
				if (!is_array($field)) {
					continue;
				}

				$id = BigTreeCMS::urlify((string)($field["id"] ?? ""));
				$prior = $stored[$id] ?? null;

				// An omitted type means "leave this field as it is", not "make it text" —
				// defaulting would count as a retype and throw the settings away.
				$type = array_key_exists("type", $field)
					? (string)$field["type"]
					: (string)($prior["type"] ?? "text");

				if ($prior !== null && (string)($prior["type"] ?? "text") === $type) {
					$row = $prior;
					$row["id"] = $id;
					$row["type"] = $type;

					foreach (["title", "subtitle"] as $key) {
						if (array_key_exists($key, $field)) {
							$row[$key] = (string)$field[$key];
						}
					}
				} else {
					$row = [
						"id" => $id,
						"type" => $type,
						"title" => (string)($field["title"] ?? ""),
						"subtitle" => (string)($field["subtitle"] ?? ""),
						"settings" => self::aiFieldSettings($field),
					];
				}

				$rows[] = $row;
			}

			return self::clean($rows);
		}

		/**
		 * The settings a new AI-authored field may carry. `required` is the only one:
		 * without it an assistant-created template could never have a required field,
		 * while every other setting (list options, image presets, subfields) is
		 * structured configuration that belongs to the admin field editor.
		 *
		 * Stored as the legacy `validation` rule string, the same source
		 * PageService's required-field gates read.
		 *
		 * @param array $field One raw AI-proposed field.
		 * @return array The settings array for a newly created field.
		 */
		private static function aiFieldSettings(array $field): array {
			$required = $field["required"] ?? false;

			if (is_string($required)) {
				$required = !in_array(strtolower(trim($required)), ["", "0", "false", "no"], true);
			}

			if (!$required) {

				return [];
			}

			return ["validation" => "required"];
		}
	}
