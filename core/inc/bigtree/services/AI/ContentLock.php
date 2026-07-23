<?php
	namespace BigTree\Services\AI;

	use BigTree\Services\LockService;
	use SQL;

	/**
	 * "Someone else has this open right now" — the assistant's half of the admin's
	 * concurrent-edit lock.
	 *
	 * The admin acquires a `bigtree_locks` row for the lifetime of an edit screen
	 * (PageEdit, ModuleEntryEdit, SettingEdit) and shows anyone arriving second a
	 * lock banner naming the holder, with the choice to take over. The assistant
	 * wrote to exactly the same records and showed nothing at all, so the one
	 * surface where a change is *deliberately* reviewed before it lands was the one
	 * surface that didn't mention the person already working on it.
	 *
	 * A validating backend returns a small `lock` descriptor naming the record;
	 * AbstractMutatingTool turns it into a sentence on the proposal card at staging
	 * time, when the user is actually looking at it.
	 *
	 * Deliberately advisory rather than a refusal. The admin lets a second editor
	 * take over a held lock and REST never blocks a write on one either, so refusing
	 * here would make the assistant stricter than every other writer — and approving
	 * *is* the take-over decision, made by a human who can now see they are making
	 * it. Losing the other editor's saved work is a separate concern, and the
	 * staleness fingerprint already covers that.
	 *
	 * Descriptor shape (the `table`/`item_id` pair the SPA's useLock hook passes):
	 *   ["table" => "bigtree_pages",  "id" => 42]
	 *   ["table" => "module:news",    "id" => "12"]
	 *   ["table" => "config:settings","id" => "site-title"]
	 *
	 * An empty or unknown descriptor produces no note, so a seam opts out by simply
	 * not emitting one.
	 */
	class ContentLock {
		/**
		 * The sentence to append to a proposal's summary, or "" when nobody else is
		 * holding the record.
		 *
		 * @param mixed $descriptor
		 * @param object|array $user The user staging the proposal.
		 */
		public static function note($descriptor, $user): string {
			$holder = self::heldBy($descriptor, $user);

			if ($holder === null) {

				return "";
			}

			$kind = self::kind((string)$descriptor["table"]);

			// Phrased as the risk that actually exists. Both copies are live: whoever
			// saves last wins, and the other editor is the one still holding an
			// unsaved buffer — so the loss runs in their favour, not against them.
			return " Note that {$holder} has this {$kind} open in the editor right now — if they save after this "
				. "is approved, their copy will overwrite it.";
		}

		/**
		 * The name of the *other* user holding a live lock on the record, or null.
		 *
		 * Null covers all the uninteresting cases: no descriptor, no lock row, a lock
		 * this same user holds (the admin refreshes rather than blocks in that case),
		 * and a lock gone stale under LockService's own five-minute rule.
		 *
		 * @param mixed $descriptor
		 * @param object|array $user
		 */
		public static function heldBy($descriptor, $user): ?string {
			if (!is_array($descriptor)) {

				return null;
			}

			$table = (string)($descriptor["table"] ?? "");
			$item_id = (string)($descriptor["id"] ?? "");

			if ($table === "" || $item_id === "" || $item_id === "0") {

				return null;
			}

			$me = (int)(is_array($user) ? ($user["id"] ?? 0) : ($user->id ?? 0));
			$row = SQL::fetch(
				"SELECT `user`, last_accessed FROM bigtree_locks WHERE `table` = ? AND item_id = ?",
				$table,
				$item_id
			);

			if (!$row || (int)$row["user"] === $me) {

				return null;
			}

			if (strtotime((string)$row["last_accessed"]) <= (time() - LockService::STALE_SECONDS)) {

				return null;
			}

			$holder = SQL::fetch("SELECT name, email FROM bigtree_users WHERE id = ?", (int)$row["user"]);

			if (!$holder) {

				return null;
			}

			$name = trim((string)($holder["name"] ?? ""));

			return $name !== "" ? $name : (string)($holder["email"] ?? "Another user");
		}

		/**
		 * What to call the record in the note. Derived from the lock table so a seam
		 * emits only the pair it already knows, rather than restating the noun.
		 */
		public static function kindOf($descriptor): string {

			return self::kind(is_array($descriptor) ? (string)($descriptor["table"] ?? "") : "");
		}

		private static function kind(string $table): string {
			if ($table === "bigtree_pages") {

				return "page";
			}

			if (strpos($table, "module:") === 0) {

				return "entry";
			}

			if ($table === "config:settings") {

				return "setting";
			}

			return "item";
		}
	}
