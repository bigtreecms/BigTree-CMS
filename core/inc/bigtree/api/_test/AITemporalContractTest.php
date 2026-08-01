<?php
	/**
	 * Audit #14 guard E1: the temporal contract.
	 *
	 * Every guard before this one asserts a relationship between two things that are
	 * both in the codebase — a tool argument against a read seam, a write seam against
	 * its gate, a route against a decline line. The clock is not in the codebase: it is
	 * a request-time fact that has to be injected, and nothing structural noticed its
	 * absence for thirteen audits. The failure that produced was not a refusal or a
	 * crash but a confidently wrong absolute date, written through a seam that had
	 * already validated it as parseable.
	 *
	 * Three legs:
	 *
	 *  - both system prompts carry the server's own date, **computed here** rather than
	 *    asserted as a literal, so a hardcoded date in TemporalContext (the same defect
	 *    one layer down) fails on any day but the day it was written;
	 *  - every seam that normalizes a model-supplied date is named, together with the
	 *    helper it normalizes through, so a fifth date-writing surface can't land
	 *    without a decision about it;
	 *  - every mutating tool argument whose name or description mentions a date is
	 *    either mapped to one of those seams or explicitly exempt.
	 */

	use BigTree\Services\AI\ColumnDomain;
	use BigTree\Services\AI\TemporalContext;
	use BigTree\Services\AIChatService;
	use BigTree\Services\PageService;
	use BigTree\Services\SearchService;
	use BigTree\Services\SettingService;

	/**
	 * Every seam that turns a model-supplied date into a stored one, and the helper it
	 * does the turning with.
	 *
	 * These are the four surfaces of audit #14's A1 table. They all validate
	 * parseability and then trust the value, which is the right check for the failure
	 * they were written for — and is only safe because the prompt now tells the model to
	 * hand relative dates through verbatim instead of resolving them itself. A fifth
	 * seam that skips `strtotime` would store `0000-00-00` and report success.
	 *
	 * @return array<string,array{0:string,1:string,2:string}> seam => [class, method, helper]
	 */
	function ai_temporal_write_seams(): array {

		return [
			"page schedule (create)" => [PageService::class, "aiPageSchedule", "strtotime"],
			"page schedule (update)" => [PageService::class, "aiPageScheduleUpdate", "strtotime"],
			// One seam for two surfaces: entry date/datetime/time columns arrive through
			// aiSiftEntryData and page date-typed template resources through
			// aiSiftResourceContent, and both land here.
			"entry and resource date columns" => [ColumnDomain::class, "dateViolation", "strtotime"],
			"date-typed settings" => [SettingService::class, "aiCheckSettingValue", "strtotime"],
		];
	}

	/**
	 * Which seam each date-carrying mutating argument reaches, or why it needs none.
	 *
	 * `content` / `data` / `value` are here because their *values* are dated even
	 * though their descriptions are about fields in general — they are the surfaces the
	 * regex leg below cannot see, so they are declared rather than derived.
	 *
	 * @return array<string,string> "tool.argument" => seam name | "exempt: reason"
	 */
	function ai_temporal_arguments(): array {

		return [
			"create_page.publish_at" => "page schedule (create)",
			"create_page.expire_at" => "page schedule (create)",
			"update_page.publish_at" => "page schedule (update)",
			"update_page.expire_at" => "page schedule (update)",
			"create_page.content" => "entry and resource date columns",
			"update_page_content.content" => "entry and resource date columns",
			"create_module_entry.data" => "entry and resource date columns",
			"update_module_entry.data" => "entry and resource date columns",
			"update_setting.value" => "date-typed settings",
			// A duration in days the server applies against its own clock, never a date
			// the model computes — one of the two temporal features that were designed as
			// capabilities and were correct all along (see get_content_alerts).
			"create_page.max_age" => "exempt: a number of days the server applies, not a date",
			"update_page.max_age" => "exempt: a number of days the server applies, not a date",
		];
	}

	/**
	 * E1a: both prompts state the server's date, and state it as a generated value.
	 */
	function test_both_system_prompts_carry_the_servers_clock() {
		// Computed here, so a literal date in TemporalContext passes for one day and
		// fails forever after.
		$today = date("Y-m-d");
		$zone = date_default_timezone_get();

		$chat = (new AIChatService())->systemPrompt(ai_fake_user(0));
		T::ok(strpos($chat, $today) !== false, "the chat prompt states today's date ({$today})");
		T::ok(strpos($chat, $zone) !== false, "the chat prompt names the server timezone ({$zone})");
		T::ok(
			stripos($chat, "never compute a date from memory") !== false,
			"the chat prompt tells the model not to do the date arithmetic itself"
		);
		T::ok(
			strpos($chat, TemporalContext::dateFormat()) !== false,
			"the chat prompt names the site's own display date format"
		);

		$search = new SearchService();
		$prompt = new ReflectionMethod($search, "aiSystemPrompt");
		$prompt->setAccessible(true);
		$search_prompt = (string)$prompt->invoke($search, ai_fake_user(0));

		// Search reads back absolute datetimes on every hit and is asked "what changed
		// this week" just as often as chat is.
		T::ok(strpos($search_prompt, $today) !== false, "the search prompt states today's date");
		T::ok(strpos($search_prompt, $zone) !== false, "the search prompt names the server timezone");
	}

	/**
	 * E1b: the anchor is generated. A date literal in the class that supplies the clock
	 * would be the same defect the class exists to close, and would be invisible on the
	 * day it was written.
	 */
	function test_the_temporal_anchor_holds_no_hardcoded_date() {
		$source = (string)file_get_contents(
			SERVER_ROOT . "core/inc/bigtree/services/AI/TemporalContext.php"
		);

		// Strip the docblocks first: the class explains itself with example dates
		// ("12/06/2026"), which are prose about the ambiguity rather than values.
		$code = (string)preg_replace('#/\*.*?\*/#s', "", $source);

		$literals = preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $code, $m) ? array_unique($m[0]) : [];

		T::equals(
			implode(", ", $literals),
			// The zero date is a stored sentinel, not a clock reading.
			"0000-00-00",
			"TemporalContext carries no date literal beyond the zero-date sentinel"
		);
		T::ok(strpos($code, "date(") !== false, "and it reads the clock through date()");
	}

	/**
	 * E1c: every named seam still normalizes through the helper it is recorded with,
	 * and the record doesn't outlive the seam.
	 */
	function test_every_temporal_write_seam_normalizes_through_its_helper() {
		$missing = [];

		foreach (ai_temporal_write_seams() as $seam => [$class, $method, $helper]) {
			T::ok(method_exists($class, $method), "{$seam}: {$class}::{$method} exists");

			if (!method_exists($class, $method)) {

				continue;
			}

			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$seam}: {$method}'s source was read");

			if (strpos($body, $helper) === false) {
				$missing[] = "{$seam}: {$method} no longer calls {$helper}";
			}
		}

		T::equals(
			implode("; ", $missing),
			"",
			"every date-writing seam resolves its value through the helper it is recorded with"
		);
	}

	/**
	 * E1d: no date-carrying mutating argument is unaccounted for, and no accounting
	 * entry names an argument or a seam that no longer exists.
	 */
	function test_every_dated_mutating_argument_is_mapped_or_exempt() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$map = ai_temporal_arguments();
		$seams = ai_temporal_write_seams();
		$declared = [];
		$unaccounted = [];

		foreach ($registry->availableTools($developer) as $tool) {
			if ($tool->kind() !== "mutate") {

				continue;
			}

			$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];

			foreach (is_array($properties) ? $properties : [] as $name => $spec) {
				$key = $tool->name() . "." . $name;
				$declared[$key] = true;
				$haystack = strtolower((string)$name . " " . (string)($spec["description"] ?? ""));

				// A name ending in _at, or prose that talks about dates. Deliberately
				// broad: a false positive costs one exemption line, and a false negative
				// costs a page that publishes on a day nobody chose.
				if (!preg_match('/\b(date|datetime|dates|clock|calendar)\b|_at$/', $haystack)) {

					continue;
				}

				if (!isset($map[$key])) {
					$unaccounted[] = $key;
				}
			}
		}

		T::equals(
			implode(", ", $unaccounted),
			"",
			"every mutating argument that carries a date is mapped to a normalization seam or exempt"
		);

		$stale = [];

		foreach ($map as $key => $seam) {
			if (!isset($declared[$key])) {
				$stale[] = "{$key} (no such argument)";

				continue;
			}

			if (strpos($seam, "exempt:") === 0) {

				continue;
			}

			if (!isset($seams[$seam])) {
				$stale[] = "{$key} → {$seam} (no such seam)";
			}
		}

		T::equals(implode(", ", $stale), "", "no temporal mapping names an argument or a seam that is gone");
	}

	/**
	 * A2: a window that has already gone by is disclosed rather than refused, and the
	 * disclosure says which party resolved the date.
	 */
	function test_a_past_schedule_warns_and_a_future_one_does_not() {
		$past = date("Y-m-d H:i:s", time() - 86400);
		$future = date("Y-m-d H:i:s", time() + 86400);

		T::ok(TemporalContext::pastWarning("publish_at", $past) !== null, "a publish date that has passed warns");
		T::ok(TemporalContext::pastWarning("publish_at", $future) === null, "a future publish date does not");
		T::ok(TemporalContext::pastWarning("expire_at", $past) !== null, "an expiry date that has passed warns");
		T::ok(
			strpos((string)TemporalContext::pastWarning("expire_at", $past), "stops being visible") !== false,
			"and it says what approving would do to the page"
		);
		T::ok(TemporalContext::pastWarning("expire_at", "") === null, "an empty value has nothing to warn about");
		T::ok(
			TemporalContext::pastWarning("publish_at", "0000-00-00 00:00:00") === null,
			"nor does the zero date, which is someone else's refusal"
		);

		// Which party did the arithmetic is the whole point of the disclosure.
		T::equals(
			TemporalContext::disclose("2026-08-04 09:00:00", "next Tuesday"),
			"2026-08-04 09:00:00 (from “next Tuesday”)",
			"a server-resolved date shows the words it came from"
		);
		T::equals(
			TemporalContext::disclose("2026-08-04 09:00:00", "2026-08-04 09:00:00"),
			"2026-08-04 09:00:00",
			"and a value the model supplied absolutely is shown plainly"
		);
		T::ok(TemporalContext::isRelative("next Tuesday"), "\"next Tuesday\" reads as relative");
		T::ok(!TemporalContext::isRelative("2026-08-04"), "a bare Y-m-d does not");
		T::ok(!TemporalContext::isRelative("2026-08-04 09:00"), "nor does Y-m-d H:i");
	}

	/**
	 * A2's second half: the re-check at approval is about the *clock*, not the value.
	 *
	 * A proposal is approvable for 24h, so a window that was in the future when the card
	 * was written can be in the past by the time anyone clicks approve — and the change
	 * that then gets written is not the one anybody read. A date that was already past at
	 * staging is a different case: the card warned about it and the user approved it
	 * knowing, so backdating stays possible.
	 */
	function test_a_schedule_that_expired_since_staging_is_refused_at_approval() {
		$past = date("Y-m-d H:i:s", time() - 86400);

		$drift = TemporalContext::scheduleDrift(["expire_at" => true], ["expire_at" => $past]);
		T::ok($drift !== null, "a window that was future at staging and is past now is refused");
		T::ok(
			strpos((string)$drift, "has since passed") !== false,
			"and the refusal says the clock moved rather than blaming the value"
		);

		T::ok(
			TemporalContext::scheduleDrift(["expire_at" => false], ["expire_at" => $past]) === null,
			"a deliberately backdated value, warned about on the card, still applies"
		);
		T::ok(
			TemporalContext::scheduleDrift([], ["expire_at" => $past]) === null,
			"and a proposal that staged no schedule has nothing to re-ask"
		);
		T::ok(
			TemporalContext::scheduleDrift(
				["publish_at" => true],
				["publish_at" => date("Y-m-d H:i:s", time() + 86400)]
			) === null,
			"a window still in the future is left alone"
		);

		$snapshot = TemporalContext::scheduleSnapshot([
			"publish_at" => date("Y-m-d H:i:s", time() + 3600),
			"expire_at" => "",
		]);
		T::equals($snapshot, ["publish_at" => true], "the snapshot records only the fields a proposal actually set");
	}
