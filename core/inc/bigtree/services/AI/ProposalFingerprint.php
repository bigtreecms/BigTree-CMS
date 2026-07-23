<?php
	namespace BigTree\Services\AI;

	use BigTreeJSONDB;
	use SQL;

	/**
	 * A staleness check for staged mutations.
	 *
	 * Proposals live up to 24 hours. Every execute seam re-checks existence,
	 * permission and value validity — but nothing asked the one question the card
	 * itself implies: *is the record still what the preview described?* A publisher
	 * reviewing "nav_title → Pricing 2026" on change #77 could approve it after the
	 * editor had wholly rewritten #77 (pending changes collapse in place), publishing
	 * content they never saw under an audit row saying they approved it.
	 *
	 * A validating backend returns a small, data-only `fingerprint` descriptor naming
	 * what the proposal is about. AbstractMutatingTool hashes it at staging and
	 * AIChatService re-hashes it at approval; a mismatch refuses instead of writing.
	 * The descriptor is deliberately declarative (no SQL from the model, no closures)
	 * so it survives the round trip through the store as plain JSON.
	 *
	 * Descriptor shapes:
	 *   ["type" => "pending_change", "id" => 77]
	 *   ["type" => "page", "id" => 42, "columns" => ["nav_title", "resources"]]
	 *   ["type" => "entry", "table" => "timber_news", "id" => 12, "columns" => [...]]
	 *   ["type" => "row", "table" => "bigtree_users", "id" => 5, "columns" => [...]]
	 *   ["type" => "json_record", "store" => "templates", "id" => "landing"]
	 *   ["type" => "setting_value", "id" => "site_title"]
	 *   ["type" => "tags", "table" => "bigtree_pages", "id" => 42]
	 *   ["type" => "open_graph", "table" => "bigtree_pages", "id" => 42]
	 *   ["type" => "composite", "parts" => [<descriptor>, <descriptor>, …]]
	 *
	 * An unknown or empty descriptor fingerprints as "" and is never compared, so a
	 * seam that opts out simply doesn't emit one. That is the escape hatch, not the
	 * default: a targeted mutation whose descriptor evaluates to "" is a proposal
	 * with no staleness protection at all, which is why the guard test asserts a
	 * non-empty runtime value rather than the mere presence of the key.
	 */
	class ProposalFingerprint {
		/** Tables a "row"/"entry" descriptor may address. Anything else is refused. */
		private const IDENTIFIER = '/^[A-Za-z0-9_]+$/';

		/**
		 * @param mixed $descriptor
		 */
		public static function compute($descriptor): string {
			if (!is_array($descriptor) || !$descriptor) {

				return "";
			}

			$type = (string)($descriptor["type"] ?? "");

			switch ($type) {
				case "pending_change":

					return self::pendingChange((int)($descriptor["id"] ?? 0));

				case "page":

					return self::row("bigtree_pages", (string)($descriptor["id"] ?? ""), $descriptor["columns"] ?? []);

				case "entry":
				case "row":

					return self::row(
						(string)($descriptor["table"] ?? ""),
						(string)($descriptor["id"] ?? ""),
						$descriptor["columns"] ?? []
					);

				case "json_record":

					return self::jsonRecord((string)($descriptor["store"] ?? ""), (string)($descriptor["id"] ?? ""));

				case "setting_value":

					return self::settingValue((string)($descriptor["id"] ?? ""));

				case "tags":

					return self::tags((string)($descriptor["table"] ?? ""), (string)($descriptor["id"] ?? ""));

				case "open_graph":

					return self::openGraph((string)($descriptor["table"] ?? ""), (string)($descriptor["id"] ?? ""));

				case "composite":

					return self::composite($descriptor["parts"] ?? []);
			}

			return "";
		}

		/**
		 * Why a descriptor could never produce a usable hash, or null when its shape
		 * is sound.
		 *
		 * compute() answers "" for a descriptor it doesn't recognize, and "" is also
		 * how a seam opts out of staleness entirely — so a misspelled type, a missing
		 * table or a forgotten `columns` list read as a deliberate opt-out and the
		 * proposal was staged with no protection at all. Worse, the same typo recurs
		 * identically at approval, so the comparison passes and nothing ever says so.
		 * Checked at staging time instead (AbstractMutatingTool), where a tool that
		 * asked for a fingerprint and can't have one fails loudly.
		 *
		 * Shape only: whether the record exists is a runtime question, and "missing"
		 * / "unset" are legitimate hashes.
		 *
		 * @param mixed $descriptor
		 */
		public static function unsupportedReason($descriptor): ?string {
			if (!is_array($descriptor) || !$descriptor) {

				return null;
			}

			$type = (string)($descriptor["type"] ?? "");
			$id = (string)($descriptor["id"] ?? "");
			$table = (string)($descriptor["table"] ?? "");
			$columns = array_filter(array_map("strval", (array)($descriptor["columns"] ?? [])));

			switch ($type) {
				case "pending_change":

					return (int)$id > 0 ? null : "a \"pending_change\" descriptor needs a change id";

				case "page":

					return $id !== "" && $columns ? null : "a \"page\" descriptor needs an id and columns";

				case "entry":
				case "row":

					return $id !== "" && $columns && preg_match(self::IDENTIFIER, $table)
						? null
						: "a \"{$type}\" descriptor needs a table, an id and columns";

				case "json_record":

					return $id !== "" && (string)($descriptor["store"] ?? "") !== ""
						? null
						: "a \"json_record\" descriptor needs a store and an id";

				case "setting_value":

					return $id !== "" ? null : "a \"setting_value\" descriptor needs a setting id";

				case "tags":
				case "open_graph":

					return $id !== "" && preg_match(self::IDENTIFIER, $table)
						? null
						: "a \"{$type}\" descriptor needs a table and an id";

				case "composite":
					$parts = $descriptor["parts"] ?? [];

					if (!is_array($parts) || !$parts) {

						return "a \"composite\" descriptor needs at least one part";
					}

					foreach ($parts as $part) {
						$reason = self::unsupportedReason($part);

						if ($reason !== null) {

							return $reason;
						}
					}

					return null;
			}

			return "\"{$type}\" is not a fingerprint type";
		}

		/**
		 * Several descriptors as one. An edit that touches a row's columns *and* its
		 * tags has to notice either moving; hashing only the half that happens to be
		 * expressible as columns is how a tag-only or Open Graph-only edit ended up
		 * with no staleness check at all.
		 *
		 * @param mixed $parts
		 */
		private static function composite($parts): string {
			if (!is_array($parts) || !$parts) {

				return "";
			}

			$hashes = [];

			foreach ($parts as $part) {
				$hash = self::compute($part);

				if ($hash === "") {

					continue;
				}

				$hashes[] = (string)($part["type"] ?? "") . ":" . $hash;
			}

			if (!$hashes) {

				return "";
			}

			sort($hashes);

			return md5(implode("\x1f", $hashes));
		}

		/**
		 * A setting's stored *value*, not its definition.
		 *
		 * update_setting used to fingerprint the JSON-DB definition record, which
		 * changes only when a developer edits the setting's type or options — so a
		 * concurrent value edit was clobbered silently on approval. Settings have
		 * neither a pending queue nor revision history, which makes that overwrite
		 * unrecoverable.
		 */
		private static function settingValue(string $id): string {
			if ($id === "") {

				return "";
			}

			$row = SQL::fetch("SELECT value FROM bigtree_settings WHERE id = ?", $id);

			// A setting with no row yet is a legitimate state (the value is the
			// definition's default until something writes one), and it is a state that
			// has to be distinguishable from any stored value.
			if (!$row) {

				return "unset";
			}

			return md5((string)$row["value"]);
		}

		/** The tag set attached to one record. */
		private static function tags(string $table, string $id): string {
			if ($id === "" || !preg_match(self::IDENTIFIER, $table)) {

				return "";
			}

			$tags = SQL::fetchAllSingle(
				"SELECT tag FROM bigtree_tags_rel WHERE `table` = ? AND entry = ? ORDER BY tag ASC",
				$table,
				$id
			) ?: [];

			return md5(implode(",", array_map("intval", $tags)));
		}

		/** One record's Open Graph row. */
		private static function openGraph(string $table, string $id): string {
			if ($id === "" || !preg_match(self::IDENTIFIER, $table)) {

				return "";
			}

			$row = SQL::fetch(
				"SELECT title, description, type, image, image_width, image_height
				 FROM bigtree_open_graph WHERE `table` = ? AND entry = ?",
				$table,
				$id
			);

			// Same reasoning as settingValue: "no record" is a real state to protect.
			if (!$row) {

				return "unset";
			}

			return md5((string)json_encode($row));
		}

		/**
		 * A queued change's identity: its `date` column (which is
		 * ON UPDATE CURRENT_TIMESTAMP, so any collapse bumps it) plus a digest of all
		 * four change blobs, because a same-second rewrite would otherwise slip past.
		 */
		private static function pendingChange(int $id): string {
			if ($id < 1) {

				return "";
			}

			$row = SQL::fetch(
				"SELECT date, changes, mtm_changes, tags_changes, open_graph_changes
				 FROM bigtree_pending_changes WHERE id = ?",
				$id
			);

			if (!$row) {

				return "missing";
			}

			return md5(implode("\x1f", [
				(string)$row["date"],
				(string)$row["changes"],
				(string)$row["mtm_changes"],
				(string)$row["tags_changes"],
				(string)$row["open_graph_changes"],
			]));
		}

		/**
		 * A digest of the named columns of one row. Only the columns the edit touches
		 * are hashed, so an unrelated change elsewhere in the record doesn't
		 * needlessly invalidate a correct proposal.
		 *
		 * @param mixed $columns
		 */
		private static function row(string $table, string $id, $columns): string {
			$columns = array_values(array_filter(array_map("strval", (array)$columns), function (string $column): bool {

				return (bool)preg_match(self::IDENTIFIER, $column);
			}));

			if ($id === "" || !$columns || !preg_match(self::IDENTIFIER, $table)) {

				return "";
			}

			sort($columns);

			$select = implode(", ", array_map(function (string $column): string {

				return "`{$column}`";
			}, $columns));
			$row = SQL::fetch("SELECT {$select} FROM `{$table}` WHERE id = ?", $id);

			if (!$row) {

				return "missing";
			}

			$parts = [];

			foreach ($columns as $column) {
				$value = $row[$column] ?? null;
				$parts[] = $column . "=" . (is_scalar($value) ? (string)$value : (string)json_encode($value));
			}

			return md5(implode("\x1f", $parts));
		}

		/** A digest of a whole JSON-DB record (templates, callouts, modules, settings). */
		private static function jsonRecord(string $store, string $id): string {
			if ($store === "" || $id === "") {

				return "";
			}

			$record = BigTreeJSONDB::get($store, $id);

			if (!$record) {

				return "missing";
			}

			return md5((string)json_encode($record));
		}
	}
