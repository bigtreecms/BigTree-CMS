<?php
	namespace BigTree\Services\AI;

	use SQL;

	/**
	 * The storage-boundary check: does a value the assistant proposes actually fit
	 * the SQL column it is about to be written into?
	 *
	 * Every audit before #10 validated a proposed value against a *definition* — the
	 * form's fields, the value's own rule string, the option domain, the type its
	 * tool declared. All of them stop where the value is handed to `SQL::insert`,
	 * and the connection runs with `sql_mode = ''` (sql-class.php), so MySQL's answer
	 * to "this doesn't fit" is to coerce and carry on: an over-long string is cut to
	 * the column width, "next Tuesday" in a `date` column becomes `0000-00-00`, a
	 * non-numeric string in an integer column becomes `0`, a value outside an `ENUM`'s
	 * domain becomes `''`. Nothing errors — `SQL::insert` returns normally, the tool
	 * reports `mode: published`, and the row holds something other than what the
	 * approver saw on the card.
	 *
	 * SettingService::aiCheckSettingValue is the positive control: it does exactly
	 * this, keyed by the setting's *type*, and has since audit #3. This class is the
	 * generalization, keyed by the *column* — which is the only thing that actually
	 * knows the width, the date format and the enum domain. It sits beside
	 * FieldOptionDomain for the same reason that class exists: one implementation, so
	 * the entry path and any future write path cannot drift apart.
	 *
	 * Run it at staging *and* at approval, per the standing "never trusted on the way
	 * back out" rule — a table can be altered inside a proposal's 24h TTL.
	 */
	class ColumnDomain {
		// Text types whose limit is in *bytes* rather than characters. `longtext` and
		// `longblob` are omitted deliberately: 4GB is not a cap anything the assistant
		// writes can reach, and checking it would only cost a strlen.
		private const TEXT_BYTE_LIMITS = [
			"tinytext" => 255,
			"text" => 65535,
			"mediumtext" => 16777215,
			"tinyblob" => 255,
			"blob" => 65535,
			"mediumblob" => 16777215,
		];

		private const INTEGER_TYPES = ["tinyint", "smallint", "mediumint", "int", "integer", "bigint"];

		private const DECIMAL_TYPES = ["decimal", "numeric", "float", "double", "real"];

		// The stored format for each temporal type, and the format an error message
		// suggests. Verbatim from aiCheckSettingValue's date handling.
		private const DATE_FORMATS = [
			"date" => "Y-m-d",
			"datetime" => "Y-m-d H:i:s",
			"timestamp" => "Y-m-d H:i:s",
			"time" => "H:i:s",
			"year" => "Y",
		];

		/**
		 * describeTable is a SHOW CREATE TABLE round trip plus a hand-written parse.
		 * The sift runs per write and the guard runs per column, so memoize per table
		 * for the life of the request.
		 *
		 * @var array<string,array<string,array<string,mixed>>>
		 */
		private static $described = [];

		/**
		 * The table's columns, keyed by name, as describeTable returns them (`type`,
		 * `size`, `options`, `allow_null`, `default`, …). Empty when the table can't
		 * be described — a lookup failure is nobody's cue to refuse every write.
		 *
		 * @return array<string,array<string,mixed>>
		 */
		public static function columns(string $table): array {
			if ($table === "" || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {

				return [];
			}

			if (array_key_exists($table, self::$described)) {

				return self::$described[$table];
			}

			try {
				$description = SQL::describeTable($table);
				$columns = is_array($description["columns"] ?? null) ? $description["columns"] : [];
			} catch (\Throwable $e) {
				$columns = [];
			}

			self::$described[$table] = $columns;

			return $columns;
		}

		/**
		 * Drop the memoized description of a table (or of everything). The AI seams
		 * never alter a table mid-request; tests that do, do.
		 */
		public static function forget(?string $table = null): void {
			if ($table === null) {
				self::$described = [];

				return;
			}

			unset(self::$described[$table]);
		}

		/**
		 * Check (and where the column's type allows, normalize) one sifted value
		 * against the column it will be written into. Returns a recoverable error
		 * message, or null when the value will survive the trip intact.
		 *
		 * $field is the AI schema entry for the column — it carries the form field's
		 * `type` and `title`, which is what the message names and what tells a
		 * checkbox's "on"/"" apart from a genuine attempt to write text into a number.
		 *
		 * @param array<string,mixed> $field The AI schema entry (column/type/title).
		 */
		public static function violation(string $table, string $column, array $field, string &$value): ?string {
			$columns = self::columns($table);

			if (!isset($columns[$column]) || !is_array($columns[$column])) {

				return null;
			}

			return self::violationForColumn($columns[$column], $field, $value);
		}

		/**
		 * The pure half of violation(): judge a value against an already-read column
		 * description. Split out so the guard tests (and any caller that already holds
		 * a describeTable result) don't need a database.
		 *
		 * @param array<string,mixed> $column The describeTable entry.
		 * @param array<string,mixed> $field  The AI schema entry (column/type/title).
		 */
		public static function violationForColumn(array $column, array $field, string &$value): ?string {
			$type = strtolower((string)($column["type"] ?? ""));
			$title = (string)($field["title"] ?? $field["column"] ?? $field["id"] ?? $column["name"] ?? "This field");
			$field_type = (string)($field["type"] ?? "");

			// An empty value has nothing to fit. Whether it is *allowed* to be empty is
			// `required`'s business, and the create/update gates own that.
			if ($value === "") {

				return null;
			}

			// Character-width types. This is the common case and the one the audit
			// opened on: a 400-word summary into a varchar(255).
			if ($type === "varchar" || $type === "char" || $type === "varbinary" || $type === "binary") {
				$max = (int)($column["size"] ?? 0);
				$length = mb_strlen($value);

				if ($max > 0 && $length > $max) {

					return self::lengthError($title, $length, $max);
				}

				return null;
			}

			if (isset(self::TEXT_BYTE_LIMITS[$type])) {
				$max = self::TEXT_BYTE_LIMITS[$type];
				$length = strlen($value);

				if ($length > $max) {

					return "“{$title}” is {$length} bytes, but the column it is stored in holds at most {$max}. "
						. "Shorten it and try again.";
				}

				return null;
			}

			if (isset(self::DATE_FORMATS[$type])) {

				return self::dateViolation($type, $title, $value);
			}

			// A checkbox is stored as "on"/"" everywhere in BigTree, including by the
			// admin's own form pipeline. If the form author pointed one at an integer
			// column that is their mismatch to fix, and refusing here would make the
			// assistant stricter than the admin for no gain.
			if ($field_type === "checkbox") {

				return null;
			}

			if (in_array($type, self::INTEGER_TYPES, true)) {
				if (!is_numeric($value)) {

					return "“{$title}” is stored in a whole-number column and \"" . self::preview($value)
						. "\" isn't a number.";
				}

				// "5.0" is a whole number written long-hand and costs nothing to accept;
				// "5.4" would be truncated to 5 in silence, which is the failure this is
				// here for.
				if ((float)$value != (float)(int)(float)$value) {

					return "“{$title}” is stored in a whole-number column and \"" . self::preview($value)
						. "\" would lose its fractional part.";
				}

				return null;
			}

			if (in_array($type, self::DECIMAL_TYPES, true)) {
				if (!is_numeric($value)) {

					return "“{$title}” is stored in a numeric column and \"" . self::preview($value)
						. "\" isn't a number.";
				}

				return null;
			}

			if ($type === "enum" || $type === "set") {

				return self::enumViolation($column, $title, $value);
			}

			return null;
		}

		/**
		 * Resolve a temporal value to the format its column stores, or explain why it
		 * can't be. Verbatim in behaviour from SettingService::aiCheckSettingValue —
		 * "publish it next Tuesday" is a plausible user sentence and the model has no
		 * reason to think it needs to resolve it, so without this the column takes
		 * `0000-00-00` and reports success.
		 */
		private static function dateViolation(string $type, string $title, string &$value): ?string {
			$raw = trim($value);

			if ($raw === "") {

				return null;
			}

			$stamp = strtotime($raw);

			if ($stamp === false) {

				return "“{$title}” is a {$type} and \"" . self::preview($raw) . "\" isn't a {$type} I can store. "
					. "Use an explicit value like \"" . date(self::DATE_FORMATS[$type]) . "\".";
			}

			$value = date(self::DATE_FORMATS[$type], $stamp);

			return null;
		}

		/**
		 * Check a value against an ENUM/SET column's own domain, matching a
		 * recognisable label back to its stored value the way FieldOptionDomain does
		 * for a `list` field. An out-of-domain ENUM value silently becomes `''`.
		 *
		 * @param array<string,mixed> $column The describeTable entry.
		 */
		private static function enumViolation(array $column, string $title, string &$value): ?string {
			$options = is_array($column["options"] ?? null) ? array_map("strval", $column["options"]) : [];

			if (!$options) {

				return null;
			}

			// A SET column stores a comma-joined subset; judge each member.
			$members = strtolower((string)($column["type"] ?? "")) === "set"
				? array_map("trim", explode(",", $value))
				: [$value];
			$resolved = [];

			foreach ($members as $member) {
				if (in_array($member, $options, true)) {
					$resolved[] = $member;

					continue;
				}

				$match = null;

				foreach ($options as $option) {
					if (strcasecmp($option, $member) === 0) {
						$match = $option;

						break;
					}
				}

				if ($match === null) {

					return "\"" . self::preview($member) . "\" isn't one of the values “{$title}” can hold. "
						. "Choose one of: " . implode(", ", array_slice($options, 0, 30))
						. (count($options) > 30 ? ", …" : "") . ".";
				}

				$resolved[] = $match;
			}

			$value = implode(",", $resolved);

			return null;
		}

		/**
		 * Characters outside the Basic Multilingual Plane — every emoji, and a range of
		 * CJK and mathematical characters — as a recoverable error, or null.
		 *
		 * The connection runs `SET NAMES 'utf8'`, which is utf8mb3, so a 4-byte
		 * sequence is not representable and MySQL (non-strict) truncates the string
		 * *at that character*. A model writing a social post or a callout headline
		 * emits emoji readily and unprompted, and the em-dashes and curly quotes it
		 * also emits are 3-byte and survive — so the failure is intermittent and looks
		 * like the model cut its own answer off.
		 *
		 * This is the sift-side half of audit #10's A2. The other half is moving the
		 * connection (and the schema) to utf8mb4, which is core work planned in
		 * plans/utf8mb4-migration.md. This check can be dropped once that has landed
		 * everywhere — but not before, because a site mid-upgrade is a mixed estate.
		 */
		public static function unrepresentable(string $title, string $value): ?string {
			if ($value === "" || !preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $value, $matches)) {

				return null;
			}

			$found = array_values(array_unique($matches[0]));
			$listed = implode(" ", array_slice($found, 0, 10));

			return "“{$title}” contains " . (count($found) === 1 ? "a character" : "characters")
				. " the database connection can't store ({$listed}) — the value would be cut off at that point "
				. "without any error. Rewrite it without " . (count($found) === 1 ? "that character" : "those characters")
				. " and try again.";
		}

		/**
		 * The field's own `maxlength` setting, as a recoverable error.
		 *
		 * `maxlength` is a real, honoured field setting — TextField/TextareaField pass
		 * it straight to the input — but it is a client-side constraint only:
		 * FieldProcessingService doesn't check it and BigTreeAutoModule::validate has
		 * no rule for it. So a field a developer deliberately capped at 60 characters
		 * (a meta title, a character-budgeted teaser) was capped for humans and
		 * uncapped for the assistant, which is the one writer with no form in front of
		 * it.
		 */
		public static function maxLengthViolation(string $title, $max, string $value): ?string {
			$max = (int)$max;

			if ($max <= 0 || $value === "") {

				return null;
			}

			$length = mb_strlen($value);

			if ($length <= $max) {

				return null;
			}

			return "“{$title}” is {$length} characters, but the field is limited to {$max}. Shorten it and try again.";
		}

		/**
		 * Columns the table requires a value for that the form can't supply: NOT NULL,
		 * no default, not auto-incrementing, and absent from the module form entirely.
		 *
		 * Deliberately reported as a disclosure rather than a refusal. In non-strict
		 * mode such a column silently takes '' or 0 on *every* write — the admin's own
		 * create does exactly the same thing, because BigTreeAutoModule::createItem
		 * builds its INSERT from the form's columns too. Refusing here would make the
		 * assistant the only writer that can't create an entry on a legacy table, which
		 * is the "stricter than the UI for no reason" failure the framework avoids.
		 *
		 * @param array<string,mixed> $form The resolved module form.
		 * @return list<string>
		 */
		public static function unsettableRequiredColumns(string $table, ?array $form): array {
			$columns = self::columns($table);

			if (!$columns) {

				return [];
			}

			$in_form = ["id" => true];

			foreach ((array)($form["fields"] ?? []) as $field) {
				$column = (string)(is_array($field) ? ($field["column"] ?? "") : "");

				if ($column !== "") {
					$in_form[$column] = true;
				}
			}

			$unsettable = [];

			foreach ($columns as $name => $column) {
				if (isset($in_form[$name]) || !is_array($column)) {

					continue;
				}

				if (!empty($column["auto_increment"]) || !empty($column["allow_null"])
					|| array_key_exists("default", $column) || isset($column["on_update"])) {

					continue;
				}

				$unsettable[] = (string)$name;
			}

			// A legacy table can have a lot of these; the note is a disclosure, not an
			// inventory, and a wall of column names in a proposal summary helps nobody.
			if (count($unsettable) > 8) {
				$unsettable = array_slice($unsettable, 0, 8);
				$unsettable[] = "…";
			}

			return $unsettable;
		}

		/**
		 * The over-length wording, matching PageService::aiPageLengthError so the two
		 * surfaces read the same to the model.
		 */
		private static function lengthError(string $title, int $length, int $max): string {

			return "“{$title}” is {$length} characters, but the column it is stored in holds at most {$max}. "
				. "Shorten it and try again.";
		}

		private static function preview(string $value): string {
			$value = trim(preg_replace('/\s+/', " ", $value) ?? "");

			return mb_strlen($value) > 60 ? mb_substr($value, 0, 60) . "…" : $value;
		}
	}
