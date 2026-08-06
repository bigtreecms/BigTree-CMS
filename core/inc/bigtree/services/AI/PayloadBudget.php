<?php
	namespace BigTree\Services\AI;

	/**
	 * The size boundary on what a turn reads.
	 *
	 * Every AI read seam caps a *value* — 300 characters per column on
	 * list_module_entries, 6,000 per field on get_page and get_module_entry, 200
	 * rows on get_page_tree. Nothing ever capped a *payload*: fifty rows times
	 * every schema column times 300 is ~375,000 characters in one tool result, a
	 * ten-field template is 60,000 from one get_page, and AgentLoop re-feeds the
	 * whole accumulated message array on every later round (AgentLoop::run).
	 *
	 * The failure that produces is not slow, it is lossy. A request over the
	 * provider's context window comes back non-2xx, the loop breaks with a provider
	 * error, and the turn dies carrying whatever it had staged — then fails
	 * identically on retry, because the same reads produce the same payloads.
	 * AIChatService::REPLAYED_HISTORY_CHARS budgets the *history* replay for exactly
	 * this reason; the in-turn accumulation sitting next to it never got the same
	 * treatment (audit #18 A1).
	 *
	 * Two ceilings, because they answer different questions:
	 *
	 *   - RESULT_CHARS bounds one tool result, applied inside the read seam. Rows
	 *     are dropped before values are shortened (a whole row read in full is
	 *     worth more than every row read in part), and both outcomes are disclosed
	 *     through the keys the payloads already carry — `has_more` for dropped
	 *     rows, `fields_truncated`/`content_truncated` for shortened values.
	 *     AgentLoop::budgetedPayload enforces the same ceiling on every result
	 *     whether or not its seam did, because "the seam bounds itself" is a
	 *     property that has to be added seam by seam and a core tool nobody
	 *     re-measured — or an extension's tool — would otherwise be one read away
	 *     from the failure below. Doing it here keeps the rows that fit; leaving it
	 *     to the backstop keeps none of them.
	 *   - TURN_CHARS bounds every tool result in a turn added together, applied in
	 *     AgentLoop next to the tool-call and proposal caps. One ceiling can't do
	 *     this job: MAX_TOOL_CALLS (40) results at RESULT_CHARS each is an order of
	 *     magnitude past any context window, so the running total is what makes the
	 *     limit legible to the model ("work from what you have") instead of to the
	 *     provider.
	 *
	 * Sized in characters rather than tokens because that is what the seams can
	 * measure without a tokenizer; roughly four characters to a token, so
	 * RESULT_CHARS is ~7.5k tokens and TURN_CHARS ~30k — which leaves the system
	 * prompt, ~45 tool definitions and the 60,000-character history replay room
	 * inside a 128k window, and degrades legibly rather than failing below that.
	 */
	class PayloadBudget {
		/** The most one tool result may contribute to the model's context. */
		const RESULT_CHARS = 30000;

		/** The most every tool result in one turn may contribute, added together. */
		const TURN_CHARS = 120000;

		/**
		 * The per-value caps a budget-constrained read may fall back to.
		 *
		 * A payload with no rows to drop (a page's content map, an entry's columns)
		 * has to shorten values instead, and the cap it lands on cannot be an
		 * arbitrary quotient: TruncatedRead recognises "you were handed this value
		 * cut off" by matching the run of characters a *known* cap ended with, so a
		 * value cut at 1,347 characters would be invisible to that refusal and
		 * writable straight back over the stored value. Quantizing to this ladder
		 * keeps every cut at a cap TruncatedRead::caps() enumerates.
		 *
		 * @var list<int>
		 */
		const VALUE_CAP_LADDER = [6000, 3000, 1500, 750, 300];

		/**
		 * How many characters a payload occupies once encoded for the model.
		 *
		 * @param mixed $payload
		 */
		public static function size($payload): int {
			$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			if (!is_string($json)) {

				return 0;
			}

			return mb_strlen($json);
		}

		/**
		 * The per-value cap a record should be read at, given how long its values
		 * actually are.
		 *
		 * Deliberately measured rather than divided. A twenty-column module entry is
		 * 120,000 characters *if every column is a long body*, which is not a real
		 * record: one field holds an article and the rest hold a title, a date and a
		 * handful of flags. Dividing the budget by the field count would cut that
		 * article to a fifth of the cap audit #10 raised it to, to defend against a
		 * shape that doesn't occur. So the declared cap is kept whenever the record
		 * as it stands fits, and the ladder is walked only for the records that
		 * genuinely don't.
		 *
		 * @param list<int> $lengths Every cappable value's real length, uncut.
		 */
		public static function capForValues(array $lengths, int $declared_cap, int $budget = self::RESULT_CHARS): int {
			if (self::totalAt($lengths, $declared_cap) <= $budget) {

				return $declared_cap;
			}

			$last = $declared_cap;

			foreach (self::VALUE_CAP_LADDER as $step) {
				if ($step >= $declared_cap) {

					continue;
				}

				$last = $step;

				if (self::totalAt($lengths, $step) <= $budget) {

					return $step;
				}
			}

			// Hundreds of fields, all long: everything is shown briefly and marked as
			// cut, which is more use than a read that returns nothing — and a record
			// shaped like that can still exceed RESULT_CHARS at the last step. That
			// residue is deliberate and bounded elsewhere: the per-turn running total
			// in AgentLoop stops the turn reading further, so it costs one oversized
			// result rather than an overflowed request.
			return $last;
		}

		/**
		 * What $lengths would occupy if every value were cut at $cap.
		 *
		 * @param list<int> $lengths
		 */
		private static function totalAt(array $lengths, int $cap): int {
			$total = 0;

			foreach ($lengths as $length) {
				$total += min((int)$length, $cap);
			}

			return $total;
		}

		/**
		 * As many leading rows as fit the budget, whole.
		 *
		 * Always at least one row: a window that returns nothing tells the model the
		 * module is empty, which is a worse lie than one oversized row. The caller
		 * reports a short return through the `has_more` it already carries.
		 *
		 * @param list<array<string,mixed>> $rows
		 * @return list<array<string,mixed>>
		 */
		public static function fitRows(array $rows, int $budget = self::RESULT_CHARS): array {
			$kept = [];
			$used = 0;

			foreach ($rows as $row) {
				$size = self::size($row);

				if ($kept && $used + $size > $budget) {

					break;
				}

				$kept[] = $row;
				$used += $size;
			}

			return $kept;
		}

		/**
		 * The refusal handed to the model in place of a single tool result too large
		 * to put in front of it (AgentLoop::budgetedPayload).
		 *
		 * Recoverable, and it names the two things that make the next attempt smaller,
		 * because the alternative — cutting the payload here — is the one thing this
		 * class must not do: an arbitrary cut through encoded JSON either breaks the
		 * encoding or hands back a value shortened at a cap TruncatedRead has never
		 * heard of, which the model may then write straight back over the stored one.
		 * A seam that expects to be big cuts itself properly instead (fitRows /
		 * capForValues) and says what it cut.
		 */
		public static function oversizedResult(string $tool, int $size): string {

			return "The result from " . ($tool !== "" ? "`" . $tool . "`" : "that tool") . " was {$size} characters, "
				. "past the " . self::RESULT_CHARS . " one tool result can occupy, so none of it can be shown to you. "
				. "Nothing failed and nothing changed — the result was only too large to read. Ask for less of it: a "
				. "narrower window (a smaller limit, or a specific id), a search instead of a listing, or one record "
				. "at a time.";
		}

		/**
		 * The refusal handed to the model once a turn has spent TURN_CHARS on tool
		 * results. Recoverable in the same way capReached's two refusals are: the
		 * turn keeps going and answers from what it has, rather than the provider
		 * rejecting an oversized request and the whole turn erroring out.
		 */
		public static function turnRefusal(): string {

			return "This turn has used its reading budget — the results you already have are as much as fits in "
				. "context. Work from those: answer, or tell the user what you would need to look at next, and stop "
				. "calling tools.";
		}
	}
