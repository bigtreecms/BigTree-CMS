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
	 *
	 * An unknown or empty descriptor fingerprints as "" and is never compared, so a
	 * seam that opts out simply doesn't emit one.
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
			}

			return "";
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
