<?php
	namespace BigTree\Services\AI;

	use BigTree\Services\FieldTypeService;

	/**
	 * Which field types the assistant may write a value for, derived from the field
	 * types' own declared taxonomy rather than from a hand-written list.
	 *
	 * Every audit through #10 read a field definition and trusted it. Audit #11 A2
	 * audited the lists doing the reading and found that ten of the eighteen entries
	 * across `AI_SIMPLE_FIELD_TYPES` / `AI_SIMPLE_RESOURCE_TYPES` / the setting lists
	 * named field types this CMS has never had — `htmleditor`, `simple-editor`,
	 * `code`, `number`, `currency`, `phone`, `email`, `color`, `select`, `radio`. A
	 * phantom entry is inert, which is why nothing ever failed; what it cost was
	 * reviewability, and under cover of it a real type (`link`) was left out of two
	 * of the three paths while being settable in the third.
	 *
	 * So the allowlist is now a derivation over `field-type-schemas.php`, which the
	 * SPA's own client already calls the source of truth: a type is settable when its
	 * declared `category` is a plain editor (`input` / `textual`) and its declared
	 * `value_type` is a scalar the model can synthesize (`string` / `bool`). Anything
	 * whose value is a file, a row id or a structure is refused, with the reason
	 * stated per category rather than as one undifferentiated wall.
	 *
	 * Two deliberate overrides sit on top of the derivation:
	 *
	 *  - DERIVED_TYPES are generated server-side on save (`route` from its source
	 *    columns, `geocoding` from its address fields). They would pass the rule and
	 *    must not: a value the model supplies is overwritten, so offering the field
	 *    would be a lie.
	 *  - REFERENCE_TYPES are settable even though their `value_type` is `resource_id`
	 *    (audit #11 B1) — the value is a `bigtree_resources` id the assistant already
	 *    reads through `list_resources` / `search_files`, so filling one is a lookup
	 *    rather than the fabrication the old refusal assumed. `resourceReferenceValue`
	 *    is the shared resolver every seam validates through.
	 *
	 * A custom field type that ships a schema through
	 * `custom/inc/bigtree/api/field-type-schemas.php` classifies itself here instead
	 * of being silently unsettable forever.
	 */
	class FieldTypeDomain {
		// Categories whose editor is a plain control over a single value.
		private const SETTABLE_CATEGORIES = ["input", "textual"];

		// Value shapes a model can write directly. `object`/`array` are structures it
		// would be guessing at; `resource_id` is handled by REFERENCE_TYPES below.
		private const SETTABLE_VALUE_TYPES = ["string", "bool"];

		// Populated by the write path itself, whatever the model supplies.
		private const DERIVED_TYPES = ["route", "geocoding"];

		// Reference types whose stored value is a bigtree_resources id (audit #11 B1).
		private const REFERENCE_TYPES = ["image-reference", "file-reference", "video-reference"];

		// How many unfillable field names a card names before it starts counting
		// (audit #14 D6).
		private const DISCLOSED_UNSETTABLE = 3;

		// Relationship types whose stored value is a list of row ids (audit #11 B2).
		// `composite` by category because their value is an array, but the array holds
		// nothing the model would be inventing — RelationDomain is the resolver.
		private const RELATION_TYPES = ["one-to-many", "many-to-many"];

		// Why each refused category is refused, in the assistant's own words. Stated
		// per category so "the assistant only sets simple text-like fields" stops
		// being the answer to five different questions.
		private const CATEGORY_REFUSALS = [
			"media" => "This field holds a file uploaded through the browser, which the assistant can't "
				. "create — upload it in the admin, or use a reference field to point at a file that is "
				. "already in the Files library.",
			"composite" => "This field holds structured content (blocks, rows, or related entries) that "
				. "needs the module's own editor in the admin.",
		];

		/** Whether the assistant may write a value for this field type. */
		public static function isSettable(string $type): bool {

			return self::refusal($type) === "";
		}

		/**
		 * Why this field type can't be written by the assistant, or "" when it can.
		 *
		 * The string is user-facing: it lands in the `reason` on a schema payload and
		 * in the error a sift returns when a value is supplied for the field anyway.
		 */
		public static function refusal(string $type): string {
			if (in_array($type, self::DERIVED_TYPES, true)) {

				return "Generated automatically when the entry is saved.";
			}

			// Settable despite a `resource_id` value_type: the value is a row id in a
			// library the assistant already reads, and ResourceReferenceDomain is the
			// resolver every seam validates it through (audit #11 B1).
			if (in_array($type, self::REFERENCE_TYPES, true)) {

				return "";
			}

			// Settable despite an `array` value_type: the array holds row ids the
			// assistant reads out of get_relation_options — the field's own target
			// table, which audit #12 B1 added because it is usually not a module's —
			// and RelationDomain is the resolver every seam validates them through
			// (audit #11 B2).
			if (in_array($type, self::RELATION_TYPES, true)) {

				return "";
			}

			$schema = FieldTypeService::schemas()[$type] ?? null;

			if (!is_array($schema)) {

				return "This field type doesn't describe a value shape the assistant can author — "
					. "it must be filled in the admin UI.";
			}

			$category = (string)($schema["category"] ?? "");
			$value_type = (string)($schema["value_type"] ?? "");

			if (
				in_array($category, self::SETTABLE_CATEGORIES, true)
				&& in_array($value_type, self::SETTABLE_VALUE_TYPES, true)
			) {

				return "";
			}

			if (isset(self::CATEGORY_REFUSALS[$category])) {

				return self::CATEGORY_REFUSALS[$category];
			}

			return "This field type can't be authored by the assistant — it must be filled in the admin UI.";
		}

		/**
		 * A `link` value that isn't a link, as a recoverable error.
		 *
		 * `link` is a real, first-class type whose stored value is a whole URL — and
		 * audit #11 B3 is that it was authorable as a *setting* and refused on pages
		 * and entries, on the strength of an allowlist that had never named it. Making
		 * it settable everywhere means the one shape check it needs has to be shared
		 * too, or the three paths disagree again the moment one of them grows a rule.
		 *
		 * Accepted: an absolute http/https/ftp URL, a mailto:/tel: address, and the
		 * tokens BigTree itself stores (`ipl://`, `irl://`, `{wwwroot}`) — a model that
		 * echoes back a value it read should not be refused for it. Everything else is
		 * refused, including a bare path: autoIPL only converts an absolute URL on this
		 * site into the token that survives the page moving, so a relative path would
		 * be stored raw and break the first time it is rendered from another depth.
		 *
		 * Returns null for any other field type — this is a per-type rule, called
		 * unconditionally by each sift.
		 */
		public static function linkShapeViolation(string $title, string $type, string $value): ?string {
			if ($type !== "link") {

				return null;
			}

			$raw = trim($value);

			if ($raw === "") {

				return null;
			}

			foreach (["ipl://", "irl://", "{wwwroot}"] as $token) {
				if (strpos($raw, $token) === 0) {

					return null;
				}
			}

			if (preg_match('/^(mailto:[^@\s]+@[^@\s]+|tel:[0-9+().\-\s]+)$/i', $raw)) {

				return null;
			}

			if (
				preg_match('#^(https?|ftp)://#i', $raw)
				&& filter_var($raw, FILTER_VALIDATE_URL) !== false
			) {

				return null;
			}

			return "“{$title}” is a link field, and \"" . (mb_strlen($raw) > 80 ? mb_substr($raw, 0, 80) . "…" : $raw)
				. "\" isn't a URL. Use a full address — \"https://example.com/page/\", or the full URL of a page "
				. "on this site, which is stored as an internal link that survives the page being moved.";
		}

		/** Whether the write path fills this field in regardless of what is supplied. */
		public static function isDerived(string $type): bool {

			return in_array($type, self::DERIVED_TYPES, true);
		}

		/** Whether this type's stored value is a bigtree_resources id. */
		public static function isResourceReference(string $type): bool {

			return in_array($type, self::REFERENCE_TYPES, true);
		}

		/** Whether this type's stored value is a list of related row ids. */
		public static function isRelation(string $type): bool {

			return in_array($type, self::RELATION_TYPES, true);
		}

		/**
		 * The reference types, for the seams that enumerate them.
		 *
		 * @return list<string>
		 */
		public static function referenceTypes(): array {

			return self::REFERENCE_TYPES;
		}

		/**
		 * The card note for fields that are real, optional, and unfillable by the
		 * assistant (audit #14 C2).
		 *
		 * The *required* half of this has been disclosed for several audits — the
		 * `incomplete_required` list on a page create, `unsettable_columns` on an entry,
		 * `aiScaffoldUnfillableColumns` on the authoring card — because a required field
		 * the assistant can't fill blocks the record from going live and something had to
		 * say so. The optional half said nothing at all, which is the quieter failure: an
		 * entry whose gallery, matrix or upload field the assistant cannot touch lands
		 * looking complete on the card and renders empty on the site, and the person
		 * approving it has no way to know a human still has to finish it.
		 *
		 * Disclosure, not refusal — the record is valid without them, and refusing would
		 * make every composite content model unauthorable. Capped at three names plus a
		 * count (D6): a form with ten composite fields would otherwise turn a warning
		 * into a paragraph, which is how warnings stop being read.
		 *
		 * @param list<string> $labels Field labels, already in "Title (id, type)" form.
		 * @return string "" when there is nothing to disclose.
		 */
		public static function optionalUnsettableNote(array $labels): string {
			$labels = array_values(array_filter(array_map("strval", $labels), function (string $label): bool {

				return trim($label) !== "";
			}));

			if (!$labels) {

				return "";
			}

			$shown = array_slice($labels, 0, self::DISCLOSED_UNSETTABLE);
			$rest = count($labels) - count($shown);
			$named = implode(", ", $shown) . ($rest > 0 ? ", and {$rest} more" : "");
			$plural = count($labels) > 1;

			return ($plural ? "These fields are" : "This field is") . " left empty because the assistant can't fill "
				. ($plural ? "them" : "it") . ": " . $named . ". "
				. ($plural ? "They are" : "It is") . " optional, so this saves fine — but if the "
				. ($plural ? "fields are" : "field is") . " meant to have content, someone has to add it in the "
				. "admin afterwards.";
		}
	}
