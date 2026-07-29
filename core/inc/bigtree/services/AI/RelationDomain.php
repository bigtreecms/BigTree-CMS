<?php
	namespace BigTree\Services\AI;

	use BigTree\Services\PermissionService;
	use BigTreeJSONDB;
	use SQL;

	/**
	 * The value domain for the two relationship field types — `one-to-many` and
	 * `many-to-many`.
	 *
	 * Audit #11 B2. The AI path knew about relations only in order to protect them:
	 * audit #4 added `aiExistingEntryRelations()` because passing `[]` to
	 * `updateItem`/`submitChange` was *deleting* an entry's relations on every edit,
	 * and audit #5 extended it to merge against a queued draft. So the seam carried
	 * relations through every write and could never author one — "file this staff
	 * member under Marketing", "relate this article to those three products" had ids
	 * coming straight out of `list_module_entries` and no way to write them.
	 *
	 * Both types store row ids and nothing else:
	 *
	 *  - `one-to-many` is a plain array in its own column
	 *    (core/admin/field-types/one-to-many/process.php:
	 *    `$field["output"] = array_values($field["input"])`), read back through the
	 *    field's `table` / `title_column` settings.
	 *  - `many-to-many` doesn't live in a column at all: it is a `__mtm__` descriptor
	 *    naming the connecting table and its two id columns, which
	 *    `AutoModuleService::validateMtm` already checks against the triples the form
	 *    itself declares. That check is the security boundary — those identifiers are
	 *    interpolated raw into SQL — and it is why this class builds the descriptor
	 *    from the field's own settings rather than accepting one from the model.
	 *
	 * What a relation still has to earn, and what this class checks: every id names a
	 * row that exists in the target table; the actor may see that row when the table
	 * belongs to a module with group-based permissions; and the field's own `max` is
	 * respected.
	 */
	class RelationDomain {
		/** Table and column names are interpolated into SQL, so they are gated hard. */
		private const IDENTIFIER = '/^[A-Za-z0-9_]+$/';

		// A relation the model can plausibly mean, bounded so a runaway list can't
		// turn one proposal into thousands of row lookups.
		private const MAX_IDS = 200;

		/**
		 * Resolve a proposed `one-to-many` value: a list of ids in the field's own
		 * target table, stored as an array in the entry's column.
		 *
		 * @param array<string,mixed> $field The AI schema entry.
		 * @param mixed $value
		 * @param object|array|null $user
		 * @return array{value?:list<string>,error?:string}
		 */
		public static function resolveOneToMany(array $field, $value, $user): array {
			$title = (string)($field["title"] ?? $field["column"] ?? $field["id"] ?? "This field");
			$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
			$table = (string)($settings["table"] ?? "");

			if (!preg_match(self::IDENTIFIER, $table)) {

				return ["error" => "“{$title}” is a relationship field whose target table isn't configured, so "
					. "nothing can be filed into it. A developer has to finish the field in Developer → Modules."];
			}

			$ids = self::normalizeIds($value);

			if ($ids === null) {

				return ["error" => "“{$title}” takes a list of entry ids — for example [12, 15]. Find them with "
					. "get_relation_options, which lists this field's own linkable rows."];
			}

			$bounded = self::boundsViolation($title, $settings, $ids);

			if ($bounded !== null) {

				return ["error" => $bounded];
			}

			$missing = self::missingRows($table, $ids, $user);

			if ($missing !== null) {

				return ["error" => "“{$title}” " . $missing];
			}

			return ["value" => array_map("strval", $ids)];
		}

		/**
		 * Resolve a proposed `many-to-many` value into the `__mtm__` descriptor the
		 * write path takes. The identifiers come from the field definition, never from
		 * the model.
		 *
		 * @param array<string,mixed> $field The AI schema entry.
		 * @param mixed $value
		 * @param object|array|null $user
		 * @return array{mtm?:array<string,mixed>,error?:string}
		 */
		public static function resolveManyToMany(array $field, $value, $user): array {
			$title = (string)($field["title"] ?? $field["column"] ?? $field["id"] ?? "This field");
			$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
			$connecting = (string)($settings["mtm-connecting-table"] ?? "");
			$my_id = (string)($settings["mtm-my-id"] ?? "");
			$other_id = (string)($settings["mtm-other-id"] ?? "");
			$other_table = (string)($settings["mtm-other-table"] ?? "");

			foreach ([$connecting, $my_id, $other_id, $other_table] as $identifier) {
				if (!preg_match(self::IDENTIFIER, $identifier)) {

					return ["error" => "“{$title}” is a relationship field that isn't fully configured, so nothing "
						. "can be filed into it. A developer has to finish the field in Developer → Modules."];
				}
			}

			$ids = self::normalizeIds($value);

			if ($ids === null) {

				return ["error" => "“{$title}” takes a list of entry ids — for example [12, 15]. Find them with "
					. "get_relation_options, which lists this field's own linkable rows."];
			}

			$bounded = self::boundsViolation($title, $settings, $ids);

			if ($bounded !== null) {

				return ["error" => $bounded];
			}

			$missing = self::missingRows($other_table, $ids, $user);

			if ($missing !== null) {

				return ["error" => "“{$title}” " . $missing];
			}

			return ["mtm" => [
				"table" => $connecting,
				"my-id" => $my_id,
				"other-id" => $other_id,
				"data" => array_map("strval", $ids),
			]];
		}

		/**
		 * A list of positive integer ids, or null when the value isn't one.
		 *
		 * A single id is accepted as itself: "file this under Marketing" is one
		 * relation, and refusing the scalar form would be a correction loop over
		 * punctuation.
		 *
		 * @param mixed $value
		 * @return list<int>|null
		 */
		private static function normalizeIds($value): ?array {
			if (is_scalar($value)) {
				$raw = trim((string)$value);
				$value = $raw === "" ? [] : [$raw];
			}

			if (!is_array($value)) {

				return null;
			}

			$ids = [];

			foreach ($value as $entry) {
				if (!is_scalar($entry)) {

					return null;
				}

				$entry = trim((string)$entry);

				if (!ctype_digit($entry) || (int)$entry < 1) {

					return null;
				}

				$ids[] = (int)$entry;
			}

			return array_values(array_unique($ids));
		}

		/**
		 * The field's own `max`, and the hard bound on how many rows one proposal may
		 * look up.
		 *
		 * @param array<string,mixed> $settings
		 * @param list<int> $ids
		 */
		private static function boundsViolation(string $title, array $settings, array $ids): ?string {
			if (count($ids) > self::MAX_IDS) {

				return "“{$title}” was given " . count($ids) . " entries, which is more than one edit should "
					. "relate at once. Do it in the admin.";
			}

			$max = (int)($settings["max"] ?? 0);

			if ($max > 0 && count($ids) > $max) {

				return "“{$title}” accepts at most {$max} " . ($max === 1 ? "entry" : "entries")
					. ", and " . count($ids) . " were given.";
			}

			return null;
		}

		/**
		 * Which of these ids don't name a row the actor can relate to, as a sentence
		 * fragment — or null when they all do.
		 *
		 * @param list<int> $ids
		 * @param object|array|null $user
		 */
		private static function missingRows(string $table, array $ids, $user): ?string {
			if (!$ids) {

				return null;
			}

			$module = self::moduleForTable($table);
			$unknown = [];
			$hidden = [];

			foreach ($ids as $id) {
				$row = SQL::fetch("SELECT * FROM `{$table}` WHERE id = ?", $id);

				if (!$row) {
					$unknown[] = $id;

					continue;
				}

				// Group-based permissions, when the target table is a module's. A
				// relation to a row the actor can't see is a relation they can't
				// review, which is the same reason every entry seam asks this.
				if ($module && $user !== null && PermissionService::userEntryLevel($user, $module, $row) === "n") {
					$hidden[] = $id;
				}
			}

			if ($unknown) {

				return "names " . (count($unknown) === 1 ? "an entry" : "entries") . " that "
					. (count($unknown) === 1 ? "doesn't" : "don't") . " exist ("
					. implode(", ", $unknown) . "). Find real ids with get_relation_options.";
			}

			if ($hidden) {

				return "names " . (count($hidden) === 1 ? "an entry" : "entries") . " you don't have access to ("
					. implode(", ", $hidden) . "), so you can't relate to "
					. (count($hidden) === 1 ? "it" : "them") . ".";
			}

			return null;
		}

		/**
		 * The module whose primary form writes this table, when there is one. Used
		 * only to ask the group-based-permission question — a table no module owns
		 * (a plain lookup table) simply has no per-row permission to ask about.
		 *
		 * @return array<string,mixed>|null
		 */
		private static function moduleForTable(string $table): ?array {
			static $cache = [];

			if (array_key_exists($table, $cache)) {

				return $cache[$table];
			}

			$cache[$table] = null;

			foreach (BigTreeJSONDB::getAll("modules") as $module) {
				foreach ((array)($module["forms"] ?? []) as $form) {
					if ((string)($form["table"] ?? "") === $table) {
						$cache[$table] = $module;

						return $cache[$table];
					}
				}
			}

			return null;
		}
	}
