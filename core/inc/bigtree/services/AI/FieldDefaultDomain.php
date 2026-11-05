<?php
	namespace BigTree\Services\AI;

	use BigTree\Services\ModuleService;

	/**
	 * The value-domain check for a field's `default` setting.
	 *
	 * Values are validated against their field definition. `default` **is** part of
	 * the field definition, and it is also a value — so without this it would enter
	 * through the door with no gates on it (`Resources::aiFieldSettings`, an
	 * `is_scalar` and a cast) and leave through the one that has them, having never
	 * been checked by either. The same string `FieldOptionDomain` refuses as an entry
	 * value would be accepted as the default that becomes *every* entry's value:
	 * `AutoModuleService::applyFormFieldDefaults` seeds it into the entry's column and
	 * `PageService::normalizePageResources` writes it into `bigtree_pages.resources`,
	 * both of them after every gate has run.
	 *
	 * So this delegates rather than restates. `FieldOptionDomain` already owns "is
	 * this one of the field's choices" and `ColumnDomain` already owns "does this fit
	 * the storage" — being separate classes with one implementation each is the whole
	 * reason those two exist, and a default judged by a third copy of their rules
	 * would be the drift they were built to prevent.
	 *
	 * Like every other domain here it normalizes in place where the rule allows it (a
	 * recognisable option *label* becomes its stored value; "next Tuesday" on a `date`
	 * becomes `Y-m-d`) and returns a recoverable error the model can act on otherwise.
	 */
	class FieldDefaultDomain {
		/**
		 * How wide the storage behind a `default` is, per surface.
		 *
		 * On the module surface the answer is literal: `scaffold_module` emits the
		 * column, so `ModuleService::columnSqlType` *is* the storage and a 5,000
		 * character default silently becomes 191 on every entry created from the form.
		 *
		 * Templates and callouts have no per-field column — a page's content lives in
		 * the one `bigtree_pages.resources` blob — so the character width is widened to
		 * that of a text column while everything the type itself implies (a `date` is
		 * still a date) is judged identically. Refusing a 200-character template
		 * default because a module column would have been VARCHAR(191) is the
		 * "stricter than the UI for no reason" failure the framework avoids.
		 */
		private const BLOB_SURFACES = ["template", "callout"];

		/**
		 * Check (and where the rule allows, normalize) a proposed `default` against the
		 * field it belongs to. Returns a recoverable error, or null.
		 *
		 * @param array<string,mixed> $field    The proposed field, for its title/id.
		 * @param string $type                  The field type the default is being stored on.
		 * @param string $surface               "template", "callout" or "module".
		 * @param array<string,mixed> $settings The settings the field will be stored with.
		 * @param string $value                 The default, normalized in place.
		 */
		public static function violation(
			array $field,
			string $type,
			string $surface,
			array $settings,
			string &$value
		): ?string {
			// An empty default is "no default" — the same thing every other domain here
			// says about "": whether the field may be empty is `required`'s business.
			if (trim($value) === "") {

				return null;
			}

			$title = (string)($field["title"] ?? "") !== ""
				? (string)$field["title"]
				: (string)($field["id"] ?? $type);
			$id = (string)($field["id"] ?? $title);

			// The option domain, on the same implementation the entry and page value
			// paths call — which is what makes a default given as a recognisable label
			// resolve to its stored value here exactly as an entry value does.
			$resolvable = ["settings" => $settings, "title" => $title, "id" => $id];
			$options = FieldOptionDomain::resolve($resolvable, $type, $id);
			$option_violation = FieldOptionDomain::violation(
				["options" => $options, "title" => $title],
				$value
			);

			if ($option_violation !== null) {

				return $option_violation;
			}

			$unrepresentable = ColumnDomain::unrepresentable($title, $value);

			if ($unrepresentable !== null) {

				return $unrepresentable;
			}

			// The field's own character budget, honoured in the browser and by every
			// value path on the server. A default is the one value on the field that
			// was never held to it.
			$max_length = ColumnDomain::maxLengthViolation(
				$title,
				ColumnDomain::configuredMaxLength($settings),
				$value
			);

			if ($max_length !== null) {

				return $max_length;
			}

			$column = self::storageColumn($type, $surface);

			if (!$column) {

				return null;
			}

			$relative = self::relativeTemporalViolation((string)$column["type"], $title, $value);

			if ($relative !== null) {

				return $relative;
			}

			return ColumnDomain::violationForColumn($column, ["type" => $type, "title" => $title], $value);
		}

		/**
		 * Refuse a relative date as a *default*.
		 *
		 * ColumnDomain resolves "next Tuesday" against the clock, which is right for a
		 * value: the model wrote it meaning the write's own moment. A default is
		 * resolved once, when the field is authored, and then read by every record
		 * created from that form for as long as the field exists — so the same
		 * resolution silently freezes "tomorrow" into one date and hands it to entries
		 * created a year later. `date`/`datetime` already carry a `default_today` /
		 * `default_now` setting for the case this is usually reaching for.
		 *
		 * Relativeness is measured, not pattern-matched: an absolute date resolves to
		 * the same instant whatever base strtotime is given, and a relative one moves
		 * with it.
		 */
		private static function relativeTemporalViolation(string $column_type, string $title, string $value): ?string {
			if (!in_array($column_type, ["date", "datetime", "timestamp", "time", "year"], true)) {

				return null;
			}

			$raw = trim($value);
			$early = strtotime($raw, 1000000000);
			$late = strtotime($raw, 1500000000);

			if ($early === false || $late === false || $early === $late) {

				return null;
			}

			return "“{$title}” is a default, so it is resolved once now and then used by every record created "
				. "from this field afterwards — \"{$raw}\" is relative to the current date, so it would freeze to "
				. "whichever day this is approved on. Use an explicit value like \"" . date("Y-m-d") . "\", or the "
				. "field's own \"default to today\" setting.";
		}

		/**
		 * The value a field's *draw-time* default would have put in the editor, or null
		 * when the field has none.
		 *
		 * Legacy BigTree honoured four settings only by pre-filling the editor's HTML
		 * control — `date/draw.php`, `datetime/draw.php`, `checkbox/draw.php`, and the
		 * browser itself for a `<select>` with no blank option — so a human who saved
		 * the form untouched stored the pre-filled value. Nothing on the server ever
		 * applied them, which left every writer that never draws a form (the assistant
		 * above all) storing a blank where the field designer configured a value.
		 *
		 *  - `date` + `default_today`    → today, `Y-m-d`
		 *  - `datetime` + `default_now`  → now, `Y-m-d H:i:s` (the server clock, which is
		 *    what legacy's convertTimestampToUser → convertTimestampFromUser round-trip
		 *    stores)
		 *  - `checkbox` + `default_checked` → the field's `custom_value`, or "on"
		 *  - `list` with `allow-empty: No` → the first resolved option. A list whose
		 *    options can't be resolved yields nothing, by the same "a misconfigured list
		 *    is nobody's cue" rule FieldOptionDomain::resolve follows.
		 *
		 * An explicit `default` is not this method's concern; callers check it first,
		 * because it always wins.
		 *
		 * @param array<string,mixed> $field A form field or template resource (type,
		 *                                   settings, column/id), or a schema entry that
		 *                                   already carries resolved `options`.
		 */
		public static function implicitDefault(array $field): ?string {
			$type = (string)($field["type"] ?? "");
			$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

			if ($type === "date" && !empty($settings["default_today"])) {

				return date("Y-m-d");
			}

			if ($type === "datetime" && !empty($settings["default_now"])) {

				return date("Y-m-d H:i:s");
			}

			if ($type === "checkbox" && !empty($settings["default_checked"])) {
				$custom = trim((string)($settings["custom_value"] ?? ""));

				return $custom !== "" ? $custom : "on";
			}

			if ($type === "list" && !FieldOptionDomain::allowsEmpty($settings)) {
				$options = is_array($field["options"] ?? null)
					? $field["options"]
					: FieldOptionDomain::resolve(
						$field,
						$type,
						(string)($field["column"] ?? $field["id"] ?? "")
					);

				if ($options) {

					return (string)($options[0]["value"] ?? "");
				}
			}

			return null;
		}

		/**
		 * The draw-time defaults a create would apply: every field absent from the
		 * supplied values, with no explicit `default` of its own, whose
		 * implicitDefault() yields a value.
		 *
		 * @param iterable<array<string,mixed>> $fields   Form fields or template resources.
		 * @param array<string,mixed>           $provided The values being written.
		 * @param string                        $key      "column" (forms) or "id" (templates).
		 * @return array<string,array{title:string,value:string}> Keyed by column/id.
		 */
		public static function implicitDefaults(iterable $fields, array $provided, string $key): array {
			$applied = [];

			foreach ($fields as $field) {
				if (!is_array($field)) {
					continue;
				}

				$id = (string)($field[$key] ?? "");

				if ($id === "" || array_key_exists($id, $provided)) {
					continue;
				}

				$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];

				if (self::explicitDefaultSatisfies($settings)) {
					continue;
				}

				$value = self::implicitDefault($field);

				if ($value === null) {
					continue;
				}

				$title = trim((string)($field["title"] ?? ""));
				$applied[$id] = ["title" => $title !== "" ? $title : $id, "value" => $value];
			}

			return $applied;
		}

		/**
		 * Whether a field's explicit `default` setting fills it. An empty default is
		 * "no default".
		 *
		 * @param array<string,mixed> $settings
		 */
		public static function explicitDefaultSatisfies(array $settings): bool {
			if (!array_key_exists("default", $settings) || !is_scalar($settings["default"])) {

				return false;
			}

			return trim((string)$settings["default"]) !== "";
		}

		/**
		 * The `defaults_applied` card row and result note for a set of applied
		 * defaults: "Published on: 2026-11-04; Featured: on". Empty when none apply.
		 *
		 * @param array<string,array{title:string,value:string}> $applied
		 */
		public static function describeApplied(array $applied): string {
			$parts = [];

			foreach ($applied as $entry) {
				$parts[] = $entry["title"] . ": " . $entry["value"];
			}

			return implode("; ", $parts);
		}

		/**
		 * The summary clause for a staged create that will fill fields from their
		 * configured defaults. Empty when none apply.
		 *
		 * @param array<string,array{title:string,value:string}> $applied
		 */
		public static function appliedNote(array $applied): string {
			if (!$applied) {

				return "";
			}

			$titles = array_column($applied, "title");

			return "The field" . (count($titles) === 1 ? " " : "s ") . implode(", ", $titles)
				. (count($titles) === 1 ? " isn't" : " aren't") . " being set, so "
				. (count($titles) === 1 ? "it takes its" : "they take their")
				. " configured default (a date default is the day this is approved).";
		}

		/**
		 * The storage a field's value lands in, as a `SQL::describeTable` entry
		 * `ColumnDomain` can judge against. Empty when the type has no column of its
		 * own (a many-to-many is a connecting table) or the SQL type is one nothing
		 * here caps.
		 *
		 * @return array<string,mixed>
		 */
		public static function storageColumn(string $type, string $surface): array {
			$sql_type = strtoupper(trim((string)ModuleService::columnSqlType($type)));

			if ($sql_type === "") {

				return [];
			}

			if (preg_match('/^(VARCHAR|CHAR)\((\d+)\)$/', $sql_type, $match)) {
				// No per-field column on this surface — see BLOB_SURFACES.
				if (in_array($surface, self::BLOB_SURFACES, true)) {

					return ["type" => "text"];
				}

				return ["type" => strtolower($match[1]), "size" => (int)$match[2]];
			}

			return ["type" => strtolower($sql_type)];
		}
	}
