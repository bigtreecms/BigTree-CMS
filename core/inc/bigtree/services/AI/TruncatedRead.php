<?php
	namespace BigTree\Services\AI;

	/**
	 * The read-fidelity boundary: refuse a write whose value is the value a read
	 * seam handed back *truncated*.
	 *
	 * Every entry and page read seam caps individual field values so a long body
	 * can't blow the model's context. Every entry and page write tool replaces the
	 * field wholesale — the tool's contract is a new value per column, and there is
	 * no within-field merge to be had. Put together, the canonical two-step the
	 * assistant exists for —
	 *
	 *     "Fix the typo in the second paragraph of the Spring Gala entry."
	 *
	 * — reads the body cut off at the cap, edits the visible text, and proposes
	 * writing that back. The proposal card previews both sides at 100–200 characters,
	 * so the approver sees a sensible opening and an ellipsis on the `from` and the
	 * `to` side alike, and approving it deletes the rest of the entry.
	 *
	 * Audit #10's B2. The disclosure half (a `fields_truncated` list on the read) and
	 * the prompt rule half both exist, but this framework's stated position is that
	 * the model is never the enforcement point, so the seam refuses too.
	 *
	 * The test is the signature of "I handed you back everything up to where you cut
	 * me off": the supplied value ends with the same run of characters the read's cut
	 * ended with, while the stored value continues past it. A genuine rewrite,
	 * shortening or append ends somewhere the model chose, not exactly where the cap
	 * fell, so the false-positive surface is effectively nil.
	 */
	class TruncatedRead {
		// How much of the cut's ending has to match. Long enough that matching it by
		// accident isn't a thing, short enough that it still fires when the model
		// touched only the opening paragraphs.
		private const TAIL = 40;

		/**
		 * Every per-value cap an AI read seam applies to a module entry column or a
		 * page content field. A write is judged against all of them, because the model
		 * may have read the value through any of these seams.
		 *
		 * @return list<int>
		 */
		public static function caps(): array {

			return [
				\BigTree\Services\AutoModuleService::AI_ENTRY_READ_CAP,       // get_module_entry
				\BigTree\Services\AutoModuleService::AI_ENTRY_LIST_VALUE_CAP, // list_module_entries
				\BigTree\Services\PageService::AI_CONTENT_FIELD_CAP,          // get_page
			];
		}

		/**
		 * Refuse a value that is the truncated form of what is stored. Returns a
		 * recoverable message, or null when the write is safe.
		 *
		 * @param list<int>|null $caps Defaults to every AI read cap.
		 */
		public static function violation(string $title, string $supplied, string $stored, ?array $caps = null): ?string {
			$supplied = self::strip($supplied);

			if ($supplied === "" || $supplied === $stored) {

				return null;
			}

			$stored_length = mb_strlen($stored);
			$supplied_length = mb_strlen($supplied);

			if ($supplied_length < self::TAIL || $supplied_length >= $stored_length) {

				return null;
			}

			$ending = mb_substr($supplied, -self::TAIL);

			foreach ($caps ?? self::caps() as $cap) {
				$cap = (int)$cap;

				if ($cap < self::TAIL || $stored_length <= $cap) {

					continue;
				}

				if (mb_substr(mb_substr($stored, 0, $cap), -self::TAIL) !== $ending) {

					continue;
				}

				return "“{$title}” was only shown to you as far as the first {$cap} characters — the stored value is "
					. "{$stored_length} characters long, and the value you supplied ends exactly where that cut fell. "
					. "Writing it back would discard everything past the cut. Ask the user to make this edit in the "
					. "admin, or change a field that came back whole.";
			}

			return null;
		}

		/**
		 * The trailing ellipsis a read seam appends isn't part of the value; a model
		 * that copies it back is still handing back the cut.
		 */
		private static function strip(string $value): string {
			$value = rtrim($value);

			while ($value !== "" && (substr($value, -3) === "…" || substr($value, -3) === "...")) {
				$value = rtrim(substr($value, 0, -3));
			}

			return $value;
		}
	}
