<?php
	namespace BigTree\Services\AI;

	use BigTree\Services\ModuleFormService;
	use BigTree\Services\PageService;

	/**
	 * One previewed value, in the form the person clicking Approve can actually check.
	 *
	 * Audit #15 A1. Every audit from #1 to #14 walked outward from the value to the
	 * row, the container, the column, the schema and the clock, and all of them stop
	 * at the moment the payload is staged. The surface none of them audited is the
	 * last one before the write happens and the only one a human reads: the proposal
	 * card. The values reaching it were the *stored* forms —
	 *
	 *  - a link-bearing field is tokenized at the sift (aiNormalizeHtmlValue), so an
	 *    internal link reached the card as `ipl://cGFnZXM6NDI=` on the `from` and the
	 *    `to` side alike, and a token the model mangled while rewriting the prose
	 *    around it is indistinguishable from an intact one. Audit #10 B3 built the
	 *    inverse and applied it at five *read* seams and zero preview seams, which
	 *    inverts the protection exactly backwards: its own docblock names the
	 *    proposal diff as "the one class of AI-caused damage the human approval gate
	 *    structurally cannot catch", and that half was still true.
	 *  - a reference field (audit #11 B1) stores a `bigtree_resources` id, so the card
	 *    read "Photo: 482" — the approver could not tell which file was being
	 *    attached, while the *model* picked it through search_files, which resolves
	 *    the name server-side already.
	 *  - a relation field (audit #11 B2) stores row ids, so the card read
	 *    `["12","15"]` with the same consequence.
	 *
	 * Resolving happens here, at the preview seam, and nowhere near the write seam:
	 * the stored form must stay tokenized, and nothing this class returns is ever
	 * written anywhere. The cap is applied *last*, after resolving, for the reason
	 * aiDenormalizeHtmlValue's own docblock gives — a decoded link is longer than its
	 * token, so cutting first would move where the cut falls.
	 */
	class PreviewValue {
		/** How much of one value a preview row carries. Applied after resolving. */
		public const CAP = 200;

		/**
		 * The most relation titles one previewed value resolves. A relation accepts up
		 * to RelationDomain::MAX_IDS ids, and a staging call is not the place to fire
		 * an unbounded row lookup; past this the row says "+N more".
		 */
		public const RELATION_CAP = 10;

		/**
		 * Render one value for a proposal card.
		 *
		 * @param mixed $value
		 * @param array<string,mixed> $field The AI schema entry, when the caller has one.
		 * @param object|array|null $user The actor, for the reference folder filter.
		 */
		public static function forHuman($value, array $field = [], $user = null): string {
			$type = (string)($field["type"] ?? "");

			if (FieldTypeDomain::isResourceReference($type)) {

				return self::cap(self::reference($value, $user));
			}

			if (FieldTypeDomain::isRelation($type)) {

				// Caps itself: the "+N more" tail is the one part of a relation row that
				// must survive the cut, so it is appended after it.
				return self::relation($value, $field);
			}

			// Decoded for every other value, not only the ones a schema says are
			// link-bearing: a preview is read by a person and stored nowhere, tokens
			// turn up in values no schema describes (a page revision's blob, a legacy
			// text column that once held a link), and two of the callers below have no
			// schema to consult at all.
			return self::cap(PageService::aiDenormalizeHtmlValue(self::stringify($value)));
		}

		/**
		 * A reference value as `filename (#482)`.
		 *
		 * An id that resolves to nothing degrades rather than throwing, and says so:
		 * that a file went away inside the proposal's 24h life is itself something the
		 * approver wants to see, and the referential re-check at approval (audit #8)
		 * refuses the write regardless. describe() is folder-filtered by design, so a
		 * deleted file and one in a folder this actor can't see are deliberately
		 * indistinguishable here — hence "no longer available" rather than a claim
		 * about which.
		 *
		 * @param mixed $value
		 * @param object|array|null $user
		 */
		private static function reference($value, $user): string {
			$raw = trim(self::stringify($value));

			if ($raw === "") {

				return "";
			}

			$described = $user !== null ? ResourceReferenceDomain::describe($raw, $user) : null;

			if ($described === null) {

				return ctype_digit($raw) ? "#{$raw} (no longer available)" : $raw;
			}

			$id = (int)$described["id"];
			$name = trim((string)($described["name"] ?? ""));

			if ($name === "") {
				$name = trim(basename((string)($described["file"] ?? "")));
			}

			return $name !== "" ? "{$name} (#{$id})" : "#{$id}";
		}

		/**
		 * A relation value as `Title (#12), Title (#15)`.
		 *
		 * @param mixed $value
		 * @param array<string,mixed> $field
		 */
		private static function relation($value, array $field): string {
			$ids = self::ids($value);

			if (!$ids) {

				return "";
			}

			$shown = array_slice($ids, 0, self::RELATION_CAP);
			$titles = self::relationTitles($field, $shown);
			$parts = [];

			foreach ($shown as $id) {
				if ($titles === null) {
					// The field itself can't be listed (a half-configured relation, which
					// RelationDomain refuses on the way in but an existing `from` value can
					// still carry). The id is all there is; don't claim the row is gone.
					$parts[] = "#{$id}";

					continue;
				}

				$title = trim((string)($titles[$id] ?? ""));
				$parts[] = $title !== "" ? "{$title} (#{$id})" : "#{$id} (no longer available)";
			}

			$rendered = self::cap(implode(", ", $parts));
			$remaining = count($ids) - count($shown);

			if ($remaining > 0) {
				$rendered .= ($rendered === "" ? "" : ", ") . "+{$remaining} more";
			}

			return $rendered;
		}

		/**
		 * id => title for the rows a relation names, through the same seam
		 * get_relation_options and the admin's own relation picker read — so the card
		 * names a row the way every other surface names it. Null when the field can't
		 * be listed at all.
		 *
		 * @param array<string,mixed> $field
		 * @param list<int> $ids Already capped by the caller; this seam takes no LIMIT.
		 * @return array<int,string>|null
		 */
		private static function relationTitles(array $field, array $ids): ?array {
			if (!$ids) {

				return [];
			}

			try {
				$options = (new ModuleFormService())->relationOptionsFor(
					$field,
					(string)($field["column"] ?? $field["id"] ?? ""),
					["ids" => implode(",", $ids)]
				);
			} catch (\Throwable $e) {

				return null;
			}

			$titles = [];

			foreach ((array)($options["items"] ?? []) as $item) {
				if (is_array($item)) {
					$titles[(int)($item["id"] ?? 0)] = (string)($item["title"] ?? "");
				}
			}

			return $titles;
		}

		/**
		 * The positive row ids a relation value holds. A sifted value is a list; a
		 * stored one is the JSON array the column holds, so both are accepted.
		 *
		 * @param mixed $value
		 * @return list<int>
		 */
		private static function ids($value): array {
			if (is_string($value)) {
				$trimmed = trim($value);
				$decoded = $trimmed !== "" && $trimmed[0] === "[" ? json_decode($trimmed, true) : null;
				$value = is_array($decoded) ? $decoded : ($trimmed === "" ? [] : [$trimmed]);
			}

			if (!is_array($value)) {

				return [];
			}

			$ids = [];

			foreach ($value as $entry) {
				if (!is_scalar($entry)) {

					continue;
				}

				$entry = trim((string)$entry);

				if (ctype_digit($entry) && (int)$entry > 0) {
					$ids[] = (int)$entry;
				}
			}

			return array_values(array_unique($ids));
		}

		/**
		 * @param mixed $value
		 */
		private static function stringify($value): string {

			return is_scalar($value) || $value === null ? (string)$value : (string)json_encode($value);
		}

		private static function cap(string $string): string {
			if (mb_strlen($string) > self::CAP) {

				return mb_substr($string, 0, self::CAP - 1) . "…";
			}

			return $string;
		}
	}
