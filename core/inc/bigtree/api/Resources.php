<?php
	namespace BigTree\Api;

	use BigTree;
	use BigTree\Services\AI\FieldDefaultDomain;

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
		 * new field starts bare apart from the few settings the assistant *can* express
		 * (see aiFieldSettings).
		 *
		 * Retyping a field drops its settings deliberately — they are configuration
		 * for the *old* field type — and the caller's field diff discloses the retype.
		 *
		 * @param mixed $fields Raw AI-proposed field list.
		 * @param array $existing Stored resources to merge against ([] when creating).
		 * @param string $surface "template" or "callout" — decides which context default
		 *                        a required `directory` setting inherits.
		 * @return array The cleaned, canonically-shaped resources list.
		 */
		public static function mergeAiFields($fields, array $existing = [], string $surface = "template"): array {
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

				$id = self::aiFieldId($field);
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
						"settings" => self::aiFieldSettings($field, $type, $surface),
					];
				}

				$rows[] = $row;
			}

			return self::clean($rows);
		}

		/**
		 * What a field id may contain. The REST path stores whatever the developer
		 * typed (`Resources::clean` only safe-encodes it) and the SPA's ResourceDesigner
		 * preserves it verbatim, so this is a validation rule, never a transformation:
		 * a field id is the key page and entry content is *stored under*, and rewriting
		 * it orphans every value already saved beneath the old key.
		 */
		public const AI_FIELD_ID_PATTERN = "/^[A-Za-z0-9_-]+$/";

		/**
		 * Template fields are additionally exposed to the render file as bare PHP
		 * variables ($page_header), so a template field id must also be a valid PHP
		 * label — `page-header` would scaffold `<?=$page-header?>`, which is a fatal
		 * error that takes down every page on the template.
		 */
		public const AI_TEMPLATE_FIELD_ID_PATTERN = "/^[A-Za-z_][A-Za-z0-9_]*$/";

		/**
		 * Field ids a callout may never use, because the placed instance stores them
		 * itself. A callout instance is `{type: "quote", headline: "…"}`, so a field
		 * called `type` overwrites the instance's own type key: draw.php:36-40 then
		 * `continue`s past the instance — every placed callout's content silently
		 * disappears from the page — and TemplateScaffold builds an include path out
		 * of editor text. Legacy refused this explicitly (admin.php:998, :8255); the
		 * shared cleaner dropped the guard on the way to the REST rewrite.
		 */
		public const RESERVED_CALLOUT_FIELD_IDS = ["type"];

		/**
		 * The reserved ids present in a resources list, if any. Shared by the REST and
		 * AI callout write paths.
		 *
		 * @param mixed $resources
		 * @return list<string>
		 */
		public static function reservedCalloutFieldIds($resources): array {
			$found = [];

			foreach ((array)$resources as $resource) {
				if (!is_array($resource)) {

					continue;
				}

				$id = strtolower(trim((string)($resource["id"] ?? "")));

				if (in_array($id, self::RESERVED_CALLOUT_FIELD_IDS, true)) {
					$found[] = $id;
				}
			}

			return array_values(array_unique($found));
		}

		/**
		 * A proposed field's id, exactly as supplied. Deliberately not urlified: this
		 * used to run through BigTreeCMS::urlify, which strips underscores to hyphens,
		 * so every shipped template's fields (page_header, page_content, …) missed the
		 * stored-record lookup below — silently retyping them to text, discarding their
		 * settings, bypassing the unconfigurable-type guard, and writing back an id no
		 * stored content lived under.
		 *
		 * @param array<string,mixed> $field
		 */
		private static function aiFieldId(array $field): string {

			return trim((string)($field["id"] ?? ""));
		}

		/**
		 * Reject any proposed field whose id isn't usable, naming the offenders so the
		 * model can correct itself on the next round.
		 *
		 * Returns null when every id is acceptable.
		 *
		 * @param mixed $fields Raw AI-proposed field list.
		 * @param string $surface "template" or "callout" — template ids are held to the
		 *                        stricter PHP-label rule.
		 */
		public static function aiFieldIdError($fields, string $surface = "template"): ?string {
			$pattern = $surface === "callout" ? self::AI_FIELD_ID_PATTERN : self::AI_TEMPLATE_FIELD_ID_PATTERN;
			$bad = [];

			foreach ((array)$fields as $field) {
				if (!is_array($field)) {

					continue;
				}

				$id = self::aiFieldId($field);

				if ($id === "") {
					$bad[] = "(empty)";

					continue;
				}

				if (!preg_match($pattern, $id)) {
					$bad[] = "\"{$id}\"";
				}
			}

			if ($surface === "callout") {
				$reserved = self::reservedCalloutFieldIds($fields);

				if ($reserved) {

					return "A callout field can't be called \"" . implode("\" or \"", $reserved) . "\" — that name is "
						. "used by the placed callout itself, and taking it makes every callout of this type render "
						. "as nothing. Pick another id (e.g. \"callout_type\").";
				}
			}

			if (!$bad) {

				return null;
			}

			$rule = $surface === "callout"
				? "letters, numbers, underscores and hyphens"
				: "letters, numbers and underscores, starting with a letter or underscore";

			return "The field id " . implode(" and ", array_unique($bad)) . " isn't usable — a field id may contain "
				. "only {$rule}. Field ids are the keys content is stored under, so they're used as supplied rather "
				. "than corrected. Choose a different id (e.g. \"page_header\") and try again.";
		}

		/**
		 * Settings a field type's draw.php hard-requires to render anything at all,
		 * beyond what its own `settings_schema` marks `required`.
		 *
		 * `matrix` bails without `settings["columns"]`, `one-to-many` without
		 * `settings["table"]` / `settings["title_column"]`, and `list` renders an empty
		 * select without `settings["list"]` — so an AI-authored one renders as nothing
		 * in the editor, and if it is also required, makes the page or entry
		 * unsaveable by anybody.
		 *
		 * The field *type* has been validated since audit #1; this is the settings half
		 * of the same rule, and the analogue for field *definitions* of what
		 * FieldTypeDomain does for field *values*. It is deliberately a small
		 * supplement to the schema-derived rule below rather than the whole rule: a
		 * static blocklist is what left `list` unguarded — and, on the value side, what
		 * audit #11 A2 found naming ten field types that do not exist.
		 */
		public const AI_RENDER_REQUIRED_SETTINGS = [
			"matrix" => ["columns"],
			"one-to-many" => ["table", "title_column"],
			"list" => ["list"],
			// Audit #12 A2. `many-to-many` doesn't live in a column at all — it is a
			// connecting table and two id columns — and none of its settings_schema
			// descriptors is marked `required`, so the schema-derived leg below finds
			// nothing to insist on. An m2m with empty settings authored clean, rendered
			// as nothing, and RelationDomain then refused to write into it.
			"many-to-many" => [
				"mtm-connecting-table",
				"mtm-my-id",
				"mtm-other-id",
				"mtm-other-table",
				"mtm-other-descriptor",
			],
		];

		/** How a surface name maps onto a field-type schema's use case. */
		private const SURFACE_USE_CASES = [
			"template" => "templates",
			"callout" => "callouts",
			// Audit #12 A2: the module surface, where scaffold_module authors a form's
			// field list exactly as create_template authors a template's.
			"module" => "modules",
		];

		/** Where a human finishes a field the assistant refused to author half-configured. */
		private const SURFACE_EDITORS = [
			"template" => "Developer → Templates",
			"callout" => "Developer → Callouts",
			"module" => "Developer → Modules → Module Designer",
		];

		/**
		 * Reject any proposed field that would be stored incompletely — either because
		 * its type needs structured configuration the assistant can't express, or
		 * because its own settings_schema marks a setting required and nothing filled
		 * it in.
		 *
		 * A field retained by id with its stored settings intact is always fine:
		 * mergeAiFields keeps that record byte-identical, so it still renders exactly
		 * as a human configured it.
		 *
		 * Returns null when every field is acceptable.
		 *
		 * @param mixed $fields   Raw AI-proposed field list.
		 * @param array $existing Stored resources ([] when creating).
		 * @param string $surface "template" or "callout", for the error's wording.
		 */
		public static function aiUnconfigurableFieldError($fields, array $existing = [], string $surface = "template"): ?string {
			$stored = [];

			foreach ($existing as $resource) {
				$id = is_array($resource) ? (string)($resource["id"] ?? "") : "";

				if ($id !== "") {
					$stored[$id] = $resource;
				}
			}

			$bad = [];
			$fixable = [];

			foreach ((array)$fields as $field) {
				if (!is_array($field)) {

					continue;
				}

				$id = self::aiFieldId($field);
				$prior = $stored[$id] ?? null;
				$type = array_key_exists("type", $field)
					? (string)$field["type"]
					: (string)($prior["type"] ?? "text");

				// Same retain condition mergeAiFields uses: an unchanged field keeps
				// its stored record, settings and all.
				if ($prior !== null && (string)($prior["type"] ?? "text") === $type) {

					continue;
				}

				$settings = self::aiFieldSettings($field, $type, $surface);
				$missing = self::aiMissingSettings($type, $settings, $surface);

				if (!$missing) {

					continue;
				}

				// `list` is the one the assistant can fix by itself, so it gets a
				// correction to act on rather than a refusal to work around.
				if ($type === "list" && $missing === ["list"]) {
					$fixable[] = "\"{$id}\"";

					continue;
				}

				$bad[] = "\"{$id}\" ({$type})";
			}

			if ($fixable) {

				return "The " . implode(" and ", array_unique($fixable)) . " list field"
					. (count($fixable) === 1 ? "" : "s") . " " . (count($fixable) === 1 ? "has" : "have")
					. " no choices, so " . (count($fixable) === 1 ? "it" : "they") . " would render as an empty "
					. "select. Supply them as \"options\", e.g. \"options\": [\"Small\", \"Medium\", \"Large\"].";
			}

			if (!$bad) {

				return null;
			}

			$where = self::SURFACE_EDITORS[$surface] ?? self::SURFACE_EDITORS["template"];

			return "A " . implode(" and ", array_unique($bad)) . " field needs structured configuration the "
				. "assistant can't author — it would render as nothing in the editor. Add "
				. (count($bad) === 1 ? "it" : "them") . " in {$where} instead, then ask again to fill in the rest.";
		}

		/**
		 * Which of a field type's must-have settings the supplied settings don't
		 * cover: the render-blocking ones above, plus everything the type's own
		 * `settings_schema` marks `required`.
		 *
		 * Deriving the second half from the schema rather than from a hardcoded list
		 * is the point — it is the server-side equivalent of the SPA's
		 * `field-settings/validate.ts`, so a field type added later (or by an
		 * extension) is covered without anyone remembering to update a list here.
		 *
		 * @param array<string,mixed> $settings The settings the field would be stored with.
		 * @return list<string>
		 */
		private static function aiMissingSettings(string $type, array $settings, string $surface): array {
			$needed = self::AI_RENDER_REQUIRED_SETTINGS[$type] ?? [];
			// The same mapping aiFieldSettings resolves the seeded defaults through —
			// read from the one const rather than spelled out again, since a second
			// hand-written copy of a mapping is the drift audit #10 A4 is about.
			$use_case = self::SURFACE_USE_CASES[$surface] ?? "templates";

			foreach (\BigTree\Services\FieldTypeService::settingsSchema($type) as $descriptor) {
				$setting_id = (string)($descriptor["id"] ?? "");

				// A `_`-prefixed descriptor is a composite editor control (image
				// options), not a stored setting of its own.
				if ($setting_id === "" || $setting_id[0] === "_" || empty($descriptor["required"])) {

					continue;
				}

				// The two filters the SPA's own validate.ts applies and this didn't, so
				// the assistant refused to author fields the admin would happily save —
				// "stricter than the UI for no reason", which the framework otherwise
				// avoids (audit #10 C2).
				if (!self::aiSettingIsVisible($descriptor, $settings, $use_case)) {

					continue;
				}

				// DirectoryControl seeds a context-specific default into storage on
				// mount, so a required directory with a `context_defaults` fallback is
				// satisfied before that seed ever runs.
				if ((string)($descriptor["control"] ?? "") === "directory"
					&& !empty($descriptor["context_defaults"][$use_case])) {

					continue;
				}

				$needed[] = $setting_id;
			}

			$missing = [];

			foreach (array_unique($needed) as $setting_id) {
				$value = $settings[$setting_id] ?? null;

				if ($value === null || $value === "" || $value === []) {
					$missing[] = $setting_id;
				}
			}

			return $missing;
		}

		/**
		 * Whether a settings descriptor is shown at all, given the other settings and
		 * the host use case. The PHP port of the SPA's
		 * `developer/field-settings/evaluate.ts` — a required setting hidden behind a
		 * `show_if` or scoped away by `contexts` is not a setting the author was ever
		 * asked for, and must not block a write.
		 *
		 * @param array<string,mixed> $descriptor
		 * @param array<string,mixed> $settings
		 */
		private static function aiSettingIsVisible(array $descriptor, array $settings, string $use_case): bool {
			$contexts = $descriptor["contexts"] ?? null;

			if (is_array($contexts) && !in_array($use_case, array_map("strval", $contexts), true)) {

				return false;
			}

			$condition = is_array($descriptor["show_if"] ?? null) ? $descriptor["show_if"] : null;

			if (!$condition) {

				return true;
			}

			$value = $settings[(string)($condition["field"] ?? "")] ?? null;
			$empty = $value === null || $value === "" || $value === [];

			if (!empty($condition["empty"])) {

				return $empty;
			}

			if (!empty($condition["not_empty"])) {

				return !$empty;
			}

			if (is_array($condition["in"] ?? null)) {

				return in_array(
					$value === null ? "" : (string)$value,
					array_map("strval", $condition["in"]),
					true
				);
			}

			if (array_key_exists("equals", $condition)) {

				return (string)($value ?? "") === (string)$condition["equals"];
			}

			return true;
		}

		/**
		 * The settings a new AI-authored field is stored with.
		 *
		 * Deliberately narrow. `required`, list `options` and a scalar `default` are the
		 * three the assistant can express meaningfully; a `directory` is inherited from
		 * the field type's own context default, exactly as the admin's DirectoryControl
		 * seeds it, so an AI-authored upload doesn't silently dump files in the site
		 * root. Everything else (image presets, matrix subfields, db-populated lists) is
		 * structured configuration that belongs to the admin field editor, and a field
		 * that needs one is refused rather than written half-configured.
		 *
		 * The module surface is the one exception, and it is deliberate rather than
		 * loose: `scaffold_module` declares a per-field `settings` object and writes it
		 * onto the form field, so what the completeness gate above checks has to be
		 * what the scaffold will actually store. Templates and callouts declare no such
		 * argument, so the narrow rule stands there.
		 *
		 * @param array $field One raw AI-proposed field.
		 * @return array The settings array for a newly created field.
		 */
		public static function aiFieldSettings(array $field, string $type = "", string $surface = "template"): array {
			// A field arrives here in one of two shapes: the assistant's
			// {id, type, options, required, default}, or an already-merged resource row
			// carrying a `settings` blob — which is what the create-approval seams hand
			// it, since what they re-gate is the payload staged from mergeAiFields. The
			// module surface takes the whole blob because scaffold_module declares one;
			// the other surfaces take only the two settings the assistant's own shape
			// can express, so this function is idempotent on its own output. Reading
			// nothing from it made a staged `list` field's options invisible at
			// approval, and create_template refused, as "no choices", a field whose
			// choices were sitting in the payload.
			$stored = is_array($field["settings"] ?? null) ? $field["settings"] : [];
			$settings = $surface === "module" ? $stored : [];

			if (array_key_exists("required", $field)) {
				$required = $field["required"];

				if (is_string($required)) {
					$required = !in_array(strtolower(trim($required)), ["", "0", "false", "no"], true);
				}

				if ($required) {
					// Stored as the legacy `validation` rule string, the same source
					// PageService's required-field gates read.
					$settings["validation"] = "required";
				}
			} elseif (is_string($stored["validation"] ?? null)) {
				// Carried through verbatim rather than rewritten to "required": a
				// stored rule string can hold more than one rule ("required email"),
				// and this is the merged-row shape, not a fresh proposal.
				$settings["validation"] = (string)$stored["validation"];
			}

			// A static list's choices, from either place the model can put them: the
			// `options` argument every field-authoring tool declares, or — on the
			// module surface, which passes a whole settings object through — the
			// `list` setting itself. Normalizing both matters because the gate above
			// only asks whether the setting is non-empty: a raw ["Small", "Large"]
			// satisfied it and then stored rows the Select can't read (audit #12 A2).
			$supplied = $field["options"] ?? null;
			$list_type = (string)($stored["list_type"] ?? "");

			if ($supplied === null && ($list_type === "" || $list_type === "static")) {
				$supplied = $stored["list"] ?? null;
			}

			$options = self::aiListOptions($supplied);

			if ($options) {
				$settings["list_type"] = "static";
				$settings["list"] = $options;
			}

			// The field's starting value, from the `default` argument every
			// field-authoring tool declares. A universal settings descriptor (see
			// FieldTypeService::universalSettingsSchema), read by
			// PageService::normalizePageResources when a page leaves the field
			// untouched, by AutoModuleService::applyFormFieldDefaults when an entry is
			// created, and by the SPA's FormRenderer when it seeds a new entry form.
			// Before audit #19 A2 all three readers existed and nothing in the product
			// — UI or assistant — could author the key they read.
			$default = self::aiFieldDefault($field, $stored, $type);

			if ($default !== null) {
				// Normalized by the same domains that judge a *value* of this field, so
				// an option given as its label lands as the stored value and a date
				// lands in the format its column keeps (audit #20 A1). A default this
				// refuses is left verbatim: aiFieldDefaultError is what turns it into
				// the recoverable error, and rewriting it here would change what that
				// error is about.
				FieldDefaultDomain::violation($field, $type, $surface, $settings, $default);
				$settings["default"] = $default;
			}

			// Mirrors spa/src/components/developer/field-settings/DirectoryControl.tsx,
			// which seeds the same per-context default on mount.
			$use_case = self::SURFACE_USE_CASES[$surface] ?? "templates";

			foreach (\BigTree\Services\FieldTypeService::settingsSchema($type) as $descriptor) {
				if ((string)($descriptor["control"] ?? "") !== "directory" || empty($descriptor["required"])) {

					continue;
				}

				$default = (string)($descriptor["context_defaults"][$use_case] ?? "");

				if ($default !== "") {
					$settings[(string)$descriptor["id"]] = $default;
				}
			}

			return $settings;
		}

		/**
		 * Per-field arguments the assistant's field shape can express, and what each
		 * ends up as once mergeAiFields has run. The carry-over branch copies only
		 * `id`/`type`/`title`/`subtitle` from the proposal, so everything here is
		 * dropped for a field retained at an unchanged type.
		 */
		private const AI_NEW_FIELD_ONLY_ARGS = ["options", "required", "default"];

		/**
		 * Reject an update that supplies a per-field setting the merge is going to
		 * discard, naming the setting and the field it was sent for.
		 *
		 * The discarding itself is right: it is what stops an `update_template` from
		 * flattening the image presets, matrix subfields and db-driven lists a human
		 * configured, which the assistant's field shape has no way to restate. What was
		 * wrong is that it happened in silence — "add Enormous to the Size dropdown" is
		 * an ordinary request, the model sends `options`, the merge drops it, the
		 * card's field diff reports only added/removed/retyped fields and shows
		 * nothing, the approval succeeds, and the model reports the change as made
		 * (audit #20 A4).
		 *
		 * A schema description is advice the model may not follow, so this is the
		 * runtime half of the same disclosure. Deliberately quiet when the supplied
		 * value matches what the field already has: restating a field's own
		 * requiredness while renaming it drops nothing, and erroring on it would make
		 * the natural "here is the complete field list" call unusable.
		 *
		 * Returns null when nothing would be dropped.
		 *
		 * @param mixed $fields   Raw AI-proposed field list.
		 * @param array $existing Stored resources.
		 * @param string $surface "template" or "callout", for the error's wording.
		 */
		public static function aiIgnoredFieldSettingsError($fields, array $existing = [], string $surface = "template"): ?string {
			$stored = [];

			foreach ($existing as $resource) {
				$id = is_array($resource) ? (string)($resource["id"] ?? "") : "";

				if ($id !== "") {
					$stored[$id] = $resource;
				}
			}

			foreach ((array)$fields as $field) {
				if (!is_array($field)) {

					continue;
				}

				$id = self::aiFieldId($field);
				$prior = $stored[$id] ?? null;
				$type = array_key_exists("type", $field)
					? (string)$field["type"]
					: (string)($prior["type"] ?? "text");

				// Only the carry-over branch drops anything; a new or retyped field is
				// built from the proposal, settings and all.
				if ($prior === null || (string)($prior["type"] ?? "text") !== $type) {

					continue;
				}

				$ignored = self::aiIgnoredFieldArgs($field, $type, $prior, $surface);

				if (!$ignored) {

					continue;
				}

				$where = self::SURFACE_EDITORS[$surface] ?? self::SURFACE_EDITORS["template"];
				// The proposal's own title when it restates one, the stored title
				// otherwise — a field carried over by id usually restates nothing, and
				// naming it by a bare id is the least useful half of the disclosure.
				$label = (string)($field["title"] ?? $prior["title"] ?? "") !== ""
					? (string)($field["title"] ?? $prior["title"])
					: $id;

				return "\"" . implode("\" and \"", $ignored) . "\" can only be set on a field being added, and "
					. "\"{$label}\" already exists — supplying " . (count($ignored) === 1 ? "it" : "them")
					. " here would change nothing, because an existing field keeps the configuration a developer set "
					. "for it (its validation rules, list choices, image sizes and matrix columns, none of which "
					. "this tool can restate). Change " . (count($ignored) === 1 ? "it" : "them") . " in {$where}, "
					. "or resend this field without " . (count($ignored) === 1 ? "that argument" : "those arguments")
					. ".";
			}

			return null;
		}

		/**
		 * Which of the new-field-only arguments this proposal supplies for an existing
		 * field *and* would actually change.
		 *
		 * @param array<string,mixed> $field The proposed field.
		 * @param array<string,mixed> $prior The stored resource record.
		 * @return list<string>
		 */
		private static function aiIgnoredFieldArgs(array $field, string $type, array $prior, string $surface): array {
			$prior_settings = is_array($prior["settings"] ?? null) ? $prior["settings"] : [];
			$proposed = self::aiFieldSettings($field, $type, $surface);
			$ignored = [];

			foreach (self::AI_NEW_FIELD_ONLY_ARGS as $arg) {
				if (!array_key_exists($arg, $field)) {

					continue;
				}

				// Each argument is compared as what it *becomes*, not as what was sent:
				// `required: true` against a field already carrying the `required`
				// validation rule drops nothing, and reporting it would make the
				// natural "here is the complete field list" call unusable.
				if ($arg === "options") {
					$changed = ($proposed["list"] ?? null) !== ($prior_settings["list"] ?? null)
						|| ($proposed["list_type"] ?? null) !== ($prior_settings["list_type"] ?? null);
				} elseif ($arg === "required") {
					$changed = self::aiSettingsAreRequired($proposed) !== self::aiSettingsAreRequired($prior_settings);
				} else {
					// Read straight off the proposal rather than through
					// aiFieldSettings, which drops a `default` on a field type that has
					// no such setting — dropping it is exactly what wants disclosing.
					$changed = (is_scalar($field["default"] ?? null) ? (string)$field["default"] : "")
						!== (is_scalar($prior_settings["default"] ?? null) ? (string)$prior_settings["default"] : "");
				}

				if ($changed) {
					$ignored[] = $arg;
				}
			}

			return $ignored;
		}

		/**
		 * Whether a settings blob makes its field required, spelled either way this CMS
		 * spells it: the `validation` rule string every pre-SPA record uses, or the
		 * standalone `required` key.
		 *
		 * @param array<string,mixed> $settings
		 */
		private static function aiSettingsAreRequired(array $settings): bool {
			if (!empty($settings["required"])) {

				return true;
			}

			$rules = is_string($settings["validation"] ?? null)
				? preg_split("/\s+/", trim($settings["validation"]), -1, PREG_SPLIT_NO_EMPTY)
				: [];

			return in_array("required", $rules ?: [], true);
		}

		/**
		 * The `default` a proposed field would be stored with, as the string every
		 * reader of the setting expects — or null when the field declares none, or
		 * when its type has no such setting to declare.
		 *
		 * A boolean is spelled "on"/"" only for `checkbox`, which is how this CMS
		 * stores a checked box everywhere (the admin's own form pipeline included).
		 * The coercion used to be unconditional, which made `"default": true` on a
		 * text field the literal string "on" (audit #20 B2).
		 *
		 * @param array<string,mixed> $field  The proposed field (assistant shape).
		 * @param array<string,mixed> $stored The field's stored settings blob, if any.
		 */
		private static function aiFieldDefault(array $field, array $stored, string $type): ?string {
			if (!self::aiTypeAcceptsDefault($type)) {

				return null;
			}

			if (array_key_exists("default", $field) && is_scalar($field["default"])) {
				$default = $field["default"];
			} elseif (array_key_exists("default", $stored) && is_scalar($stored["default"])) {
				$default = $stored["default"];
			} else {

				return null;
			}

			if (is_bool($default) && $type === "checkbox") {

				return $default ? "on" : "";
			}

			return (string)$default;
		}

		/**
		 * Whether this field type declares a `default` setting at all.
		 *
		 * Asked of the declaration rather than restated here: audit #20 A2 scoped the
		 * universal descriptor to the value types one scalar can mean something for,
		 * and a second copy of that rule on the write side is the drift the scoping was
		 * meant to end.
		 */
		private static function aiTypeAcceptsDefault(string $type): bool {
			foreach (\BigTree\Services\FieldTypeService::settingsSchema($type) as $descriptor) {
				if ((string)($descriptor["id"] ?? "") === "default") {

					return true;
				}
			}

			return false;
		}

		/**
		 * Reject a proposed `default` that isn't a legal value of the field it is being
		 * set on — the check that never existed, on the one field setting whose stored
		 * value later becomes record content (audit #20 A1).
		 *
		 * Runs on the same fields, under the same retain rule, as
		 * aiUnconfigurableFieldError: a field carried over by id at an unchanged type
		 * keeps the default a human configured, and re-judging it would refuse an
		 * update for a setting this proposal isn't touching.
		 *
		 * Returns null when every default is acceptable.
		 *
		 * @param mixed $fields   Raw AI-proposed field list.
		 * @param array $existing Stored resources ([] when creating).
		 * @param string $surface "template", "callout" or "module".
		 */
		public static function aiFieldDefaultError($fields, array $existing = [], string $surface = "template"): ?string {
			$stored = [];

			foreach ($existing as $resource) {
				$id = is_array($resource) ? (string)($resource["id"] ?? "") : "";

				if ($id !== "") {
					$stored[$id] = $resource;
				}
			}

			foreach ((array)$fields as $field) {
				if (!is_array($field)) {

					continue;
				}

				$id = self::aiFieldId($field);
				$prior = $stored[$id] ?? null;
				$type = array_key_exists("type", $field)
					? (string)$field["type"]
					: (string)($prior["type"] ?? "text");

				if ($prior !== null && (string)($prior["type"] ?? "text") === $type) {

					continue;
				}

				$settings = self::aiFieldSettings($field, $type, $surface);
				$blob = is_array($field["settings"] ?? null) ? $field["settings"] : [];
				$supplied = array_key_exists("default", $field) || array_key_exists("default", $blob);

				// A default the type has no setting for is refused rather than dropped:
				// dropping it hands the model a successful card for a request it didn't
				// fulfil, which is the defect A4 is about.
				if ($supplied && !self::aiTypeAcceptsDefault($type)) {
					$label = (string)($field["title"] ?? "") !== "" ? (string)$field["title"] : $id;

					return "\"{$label}\" is a {$type} field, whose value isn't a single value, so it has no "
						. "default — a default set on one is written into every record as a bare string that "
						. "nothing reading the field can use. Remove \"default\" from this field.";
				}

				$value = (string)($settings["default"] ?? "");
				$violation = FieldDefaultDomain::violation($field, $type, $surface, $settings, $value);

				if ($violation !== null) {

					return "The default for \"" . ((string)($field["title"] ?? "") !== "" ? (string)$field["title"] : $id)
						. "\" isn't a value that field can hold. " . $violation;
				}
			}

			return null;
		}

		/**
		 * Normalize a proposed set of list choices into the {value, description} rows
		 * the `list` field type's static list stores.
		 *
		 * Accepts the two shapes a model naturally produces: a flat list of strings,
		 * or objects carrying an explicit value and label.
		 *
		 * @param mixed $options
		 * @return list<array{value:string,description:string}>
		 */
		private static function aiListOptions($options): array {
			if (!is_array($options)) {

				return [];
			}

			$rows = [];

			foreach ($options as $option) {
				if (is_array($option)) {
					$value = trim((string)($option["value"] ?? $option["description"] ?? $option["label"] ?? ""));
					$description = trim((string)($option["description"] ?? $option["label"] ?? $option["value"] ?? ""));
				} else {
					$value = trim((string)$option);
					$description = $value;
				}

				if ($value === "" && $description === "") {

					continue;
				}

				$rows[] = ["value" => $value, "description" => $description !== "" ? $description : $value];
			}

			return $rows;
		}
	}
