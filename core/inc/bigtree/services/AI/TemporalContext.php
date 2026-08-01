<?php
	namespace BigTree\Services\AI;

	/**
	 * The clock the assistant was never given.
	 *
	 * Every other domain class in this directory checks a value against something
	 * that exists in the codebase — a field's declared type, a column's width, a
	 * module icon's closed vocabulary. This one carries the opposite kind of fact:
	 * *what time it is*, which is nowhere in the codebase and has to be injected at
	 * request time. Nothing structural notices its absence, which is exactly why it
	 * went missing for thirteen audits.
	 *
	 * The failure it closes (audit #14 A1) is not a refusal or a crash. The model
	 * writes dates on four surfaces — `publish_at`/`expire_at` on a page, `date` /
	 * `datetime` / `time` entry columns, `date`-typed template resources, and
	 * date-typed settings — and every one of those seams validates *parseability*
	 * with `strtotime()` and then trusts the value. That is the right check for the
	 * failure it was written for ("next Tuesday" arriving verbatim), and it is the
	 * less likely failure, because the tool descriptions used to show a hardcoded
	 * absolute example and thereby steer the model into computing a date from its own
	 * training-cutoff sense of "now". `strtotime` accepts it, the column stores it,
	 * the card shows it, and the page publishes on a date nobody chose.
	 *
	 * Three legs, all closed here:
	 *
	 *   1. **Relative language resolved by the wrong party.** The server can resolve
	 *      "next Monday" correctly and the model cannot, so `promptLines()` tells it
	 *      to pass the user's words through verbatim and let the seams' existing
	 *      `strtotime` pass do the work.
	 *   2. **Format ambiguity.** `strtotime("12/06/2026")` is December 6 (US m/d/y)
	 *      and `strtotime("12-06-2026")` is 12 June. The site configures its own
	 *      human format (`$bigtree["config"]["date_format"]`), so the prompt names it
	 *      — otherwise on a `d/m/Y` site the assistant silently disagrees with every
	 *      date the user reads on screen.
	 *   3. **Read-side recency.** `get_pending_changes`, `get_audit_trail`,
	 *      `get_page_revisions` and every entry read hand back absolute datetimes.
	 *      "What changed this week?" is arithmetic against a "now" the model was
	 *      guessing at.
	 *
	 * The positive controls were one file over: `get_content_alerts` computes
	 * staleness server-side and `max_age` is a duration the server applies — the two
	 * temporal features designed as capabilities work, and the ones the model does
	 * arithmetic for did not.
	 *
	 * Rejected alternative: a `get_current_time` read tool. It costs a round, and the
	 * model has no reason to call it before answering "what changed this week", which
	 * is precisely when it is needed.
	 *
	 * Everything here is **generated, never literal** — a hardcoded date in this file
	 * would be the same defect one layer down, and `AITemporalContractTest` computes
	 * its expectations rather than asserting a string so a literal fails the guard.
	 */
	class TemporalContext {
		/**
		 * The server's notion of now, to the minute. One seam so a test can compare
		 * against `date()` rather than against a literal.
		 */
		public static function now(): string {

			return date("Y-m-d H:i");
		}

		public static function timezone(): string {

			return date_default_timezone_get();
		}

		/**
		 * The human date format this site's admin renders and its date pickers seed
		 * from. Named in the prompt because it is the thing the model can't infer and
		 * the user can't help disagreeing with: the same eight characters mean two
		 * different days under `m/d/Y` and `d/m/Y`.
		 */
		public static function dateFormat(): string {
			global $bigtree;

			$format = trim((string)($bigtree["config"]["date_format"] ?? ""));

			return $format !== "" ? $format : "m/d/Y";
		}

		/**
		 * The temporal anchor block, as prompt lines so callers can splice it into
		 * their own prompt array (the shape `PromptGuard::safetyRules()` uses).
		 *
		 * The rule matters as much as the facts: told only the date, a model still
		 * resolves "next Monday" itself and gets it wrong whenever its arithmetic or
		 * its week boundary differs from the server's. Told to pass the words through,
		 * the resolution happens in the one place that can be right.
		 *
		 * @return list<string>
		 */
		public static function promptLines(): array {

			return [
				"The clock:",
				"- The current date and time on this server is " . self::now() . " (" . self::timezone() . "). "
					. "Today is " . date("l, F jS Y") . ".",
				"- Never compute a date from memory, and never assume today's date is anything other than the one "
					. "above — your own sense of \"now\" is from your training data and is not this site's clock.",
				"- If the user gives a relative date (\"next Monday\", \"in two weeks\", \"end of the month\"), pass "
					. "their words through verbatim in the tool argument. The server resolves them against the clock "
					. "above and shows the resolved date on the confirmation card.",
				"- This site displays dates to people in " . self::dateFormat() . " format. A numeric date the user "
					. "types means what that format says it means — when a date like 12/06/2026 could be read two "
					. "ways, ask which they meant rather than guessing.",
				"- Dates that tools hand back (pending changes, audit entries, revisions, entry fields) are absolute "
					. "server-time values. Compare them against the clock above, not against a remembered date, when "
					. "answering questions about what is recent, overdue or stale.",
			];
		}

		/**
		 * Whether a supplied date/time string is not the value the model computed but
		 * the user's own words — i.e. something the server had to resolve.
		 *
		 * Used for preview disclosure only: `publish_at: 2026-08-04 (from "next
		 * Tuesday")` tells the person approving the card which party did the
		 * arithmetic, which is the difference between a date they can sanity-check and
		 * one they have to take on faith. Deliberately loose — the test is "did this
		 * look like a machine value", so anything that is not a bare
		 * `Y-m-d`/`Y-m-d H:i(:s)` reads as relative and gets disclosed.
		 */
		public static function isRelative(string $raw): bool {
			$raw = trim($raw);

			if ($raw === "") {

				return false;
			}

			return preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $raw) !== 1;
		}

		/**
		 * The non-fatal note that belongs on a proposal whose date has already gone by.
		 *
		 * Refusing would be wrong: backdating `publish_at` is a legitimate thing to
		 * want, and an unparseable value is already refused one seam over. But a
		 * window in the past means something specific and invisible — an `expire_at`
		 * that has passed hides the page the instant the card is approved (`cms.php`
		 * enforces the window at render) — and a reviewer who does not know today's
		 * date learns nothing from an absolute value shown without framing.
		 *
		 * Deliberately scoped to `publish_at`/`expire_at`, the two fields whose whole
		 * meaning is "relative to now". An entry's `date` column is legitimately in the
		 * past on an event archive, a published-on date and any historical record.
		 *
		 * Re-asked at approval as well as at staging, and that is the point rather than
		 * a formality: a proposal is approvable for `ProposalStore::TTL_SECONDS` (24h),
		 * so a window resolved from staging-time "now" can be in the past by the time
		 * anyone clicks approve.
		 *
		 * @param string $field "publish_at" or "expire_at".
		 * @param string $value The normalized, absolute value.
		 * @return string|null Null when the value is empty, unparseable (someone else's
		 *   refusal) or still in the future.
		 */
		public static function pastWarning(string $field, string $value): ?string {
			$value = trim($value);

			if ($value === "" || $value === "0000-00-00 00:00:00") {

				return null;
			}

			$stamp = strtotime($value);

			if ($stamp === false || $stamp >= time()) {

				return null;
			}

			$when = date("Y-m-d H:i", $stamp);

			if ($field === "expire_at") {

				return "The expiry date {$when} has already passed (it is now " . self::now() . "), so this page "
					. "stops being visible on the site the moment this is approved. If that isn't what you meant, "
					. "ask for a later expiry date.";
			}

			return "The publish date {$when} has already passed (it is now " . self::now() . "), so this goes live "
				. "immediately rather than being scheduled. If you meant a future date, ask for it explicitly.";
		}

		/**
		 * Both warnings for a resolved window, joined for a single `warning` preview
		 * key. Empty string when neither applies, so a caller can test it without
		 * unwrapping a null.
		 */
		public static function scheduleWarning(?string $publish_at, ?string $expire_at): string {
			$notes = [];

			foreach (["publish_at" => $publish_at, "expire_at" => $expire_at] as $field => $value) {
				$note = self::pastWarning($field, (string)$value);

				if ($note !== null) {
					$notes[] = $note;
				}
			}

			return implode(" ", $notes);
		}

		/**
		 * Which of a proposal's schedule fields were still in the future when the card
		 * was written, stored in the payload so the approval can tell the two cases
		 * apart.
		 *
		 * This is what makes the approval-time re-check a warning-plus-refusal rather
		 * than a blanket refusal (audit #14 D2). A value that was *already* past at
		 * staging was deliberate — backdating a publish_at is a legitimate thing to
		 * want, the card carried `pastWarning`'s note about it, and the user approved it
		 * knowing. A value that was in the future at staging and is past by the time
		 * anyone clicks approve is the two-phase design leaking: a proposal is
		 * approvable for `ProposalStore::TTL_SECONDS` (24h), which is exactly long
		 * enough for a schedule to expire between the card and the click, and the change
		 * that then gets written is not the one anybody read.
		 *
		 * @param array<string,mixed> $values field => resolved absolute value.
		 * @return array<string,bool> field => was in the future at staging. Fields with
		 *   no value are omitted, so an absent key means "nothing to re-ask".
		 */
		public static function scheduleSnapshot(array $values): array {
			$snapshot = [];

			foreach (["publish_at", "expire_at"] as $field) {
				$value = trim((string)($values[$field] ?? ""));

				if ($value === "" || $value === "0000-00-00 00:00:00") {

					continue;
				}

				$stamp = strtotime($value);

				if ($stamp === false) {

					continue;
				}

				$snapshot[$field] = $stamp >= time();
			}

			return $snapshot;
		}

		/**
		 * The approval-time refusal for a schedule that expired while the card sat in
		 * the store. Null when nothing drifted — including when the value was already
		 * in the past at staging, which the card disclosed and the user approved.
		 *
		 * @param array<string,bool> $snapshot From scheduleSnapshot() at staging.
		 * @param array<string,mixed> $values field => the value about to be written.
		 */
		public static function scheduleDrift(array $snapshot, array $values): ?string {
			foreach (["publish_at", "expire_at"] as $field) {
				if (empty($snapshot[$field])) {

					continue;
				}

				$value = trim((string)($values[$field] ?? ""));

				if ($value === "" || self::pastWarning($field, $value) === null) {

					continue;
				}

				$when = date("Y-m-d H:i", (int)strtotime($value));

				if ($field === "expire_at") {

					return "This card was proposed with an expiry date of {$when}, which was in the future then and "
						. "has since passed (it is now " . self::now() . "). Approving it now would take the page off "
						. "the site immediately rather than expiring it later. Ask again with a new expiry date.";
				}

				return "This card was proposed to go live at {$when}, which was in the future then and has since "
					. "passed (it is now " . self::now() . "). Approving it now publishes immediately rather than on "
					. "the schedule described. Ask again with a new date if that isn't what you want.";
			}

			return null;
		}

		/**
		 * `2026-08-04 09:00:00 (from "next Tuesday")` — the preview rendering of a
		 * value the server resolved, and the plain value when the model supplied an
		 * absolute one. Which party did the arithmetic is the one thing the approver
		 * needs and the card could never show.
		 */
		public static function disclose(string $resolved, string $raw): string {
			if (!self::isRelative($raw)) {

				return $resolved;
			}

			$raw = trim($raw);

			if (mb_strlen($raw) > 60) {
				$raw = mb_substr($raw, 0, 59) . "…";
			}

			return $resolved . " (from “" . $raw . "”)";
		}
	}
