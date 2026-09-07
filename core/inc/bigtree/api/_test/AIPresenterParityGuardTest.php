<?php
	/**
	 * Audit #28 guard E5: an AI read seam against the REST presenter of the same
	 * record.
	 *
	 * `search_users` returned id/name/email/level/company/timezone/daily_digest while
	 * `UserService::presentFull` gives self-or-admin `permissions` and `alerts` too.
	 * The consequence is the shape audits #24 B1 and #27 B1 both had: the only decline
	 * line in the neighbourhood is "Changing user levels, permissions or passwords",
	 * which is about *writing*, so "who can publish News?" hit no tool and no wall —
	 * and every decline line is spliced into the system prompt beside *do not
	 * improvise a workaround*. It was also a read/write asymmetry on the assistant's
	 * own surface: update_user writes `alerts` and could read back only `daily_digest`.
	 *
	 * Neither existing wording guard sees it. Audit #24's E5 and #27's E4 ask their
	 * question only of *declined* families, and `users` is a covered family.
	 *
	 * The pair list is deliberately explicit rather than discovered: a blanket version
	 * would fail on a dozen deliberate shape differences (a search row is not a detail
	 * payload) and would be noise, not a guard.
	 */

	use BigTree\Services\PageService;
	use BigTree\Services\SearchService;
	use BigTree\Services\UserService;

	/**
	 * AI read seam ↔ REST presenter pairs.
	 *
	 * `presenter` may name several methods, since a presenter that layers on another
	 * (presentFull over presentList) emits its keys across both. Every key the
	 * presenter emits must appear in the seam, or in `omitted` with a reason.
	 *
	 * @return array<string,array{seam:array{0:string,1:string},presenter:array{0:string,1:list<string>},omitted:array<string,string>}>
	 */
	function ai_presenter_pairs(): array {

		return [
			"search_users" => [
				"seam" => [SearchService::class, "searchUsers"],
				"presenter" => [UserService::class, ["presentList", "presentFull"]],
				"omitted" => [
					"two_factor_enabled" => "whether an account has 2FA configured is a security-posture "
						. "fact the assistant never acts on — managing two-factor and passkeys is an "
						. "explicit non-tool — so it stays off a search payload the model reads in bulk",
				],
			],
			// Audit #29 B2. The tool's own description says "use this to find the
			// revision to restore", and both lists returned the same five display
			// columns — nothing about whether a revision is still restorable. The four
			// facts that answer come from revisionRestorability, which both presenters
			// call, so they cannot differ by construction; what this pair watches is
			// the half either surface writes out for itself.
			"get_page_revisions" => [
				"seam" => [PageService::class, "aiPageRevisions"],
				"presenter" => [PageService::class, ["listRevisions"]],
				"omitted" => [
					"page" => "the page every revision belongs to is the seam's own `page_id`, returned "
						. "once beside `page_title` rather than repeated on each of up to fifty rows",
					"author" => "a user id the model can do nothing with; the seam resolves it to "
						. "`author_name`, which is what \"who saved this?\" actually asks",
					"saved_description" => "returned as `description`, beside the `saved` flag that says "
						. "whether there is one — the column name is the storage's, not the reader's",
				],
			],
		];
	}

	/**
	 * The payload keys a method emits: `"key" => …` inside an array literal, plus
	 * `$out["key"] = …` for the ones a conditional adds afterwards.
	 *
	 * @return list<string>
	 */
	function ai_presenter_keys(string $class, string $method): array {
		if (!class_exists($class) || !method_exists($class, $method)) {

			return [];
		}

		$reflection = new ReflectionMethod($class, $method);
		$file = $reflection->getFileName();

		if (!$file || !is_readable($file)) {

			return [];
		}

		$lines = file($file);
		$body = implode("", array_slice(
			$lines,
			$reflection->getStartLine() - 1,
			$reflection->getEndLine() - $reflection->getStartLine() + 1
		));

		preg_match_all('/"([a-z_0-9]+)"\s*=>/', $body, $literal);
		preg_match_all('/\["([a-z_0-9]+)"\]\s*=(?![=>])/', $body, $assigned);

		return array_values(array_unique(array_merge($literal[1] ?? [], $assigned[1] ?? [])));
	}

	function test_every_ai_read_seam_matches_its_rest_presenter() {
		$pairs = ai_presenter_pairs();
		T::ok(count($pairs) > 0, "at least one seam/presenter pair is declared");

		foreach ($pairs as $tool => $pair) {
			$seam_keys = ai_presenter_keys($pair["seam"][0], $pair["seam"][1]);
			T::ok(count($seam_keys) > 3, "{$tool}'s payload keys were parsed");

			$presenter_keys = [];

			foreach ($pair["presenter"][1] as $method) {
				$presenter_keys = array_merge(
					$presenter_keys,
					ai_presenter_keys($pair["presenter"][0], $method)
				);
			}

			$presenter_keys = array_values(array_unique($presenter_keys));
			T::ok(count($presenter_keys) > 3, "{$tool}'s REST presenter keys were parsed");

			$missing = [];

			foreach ($presenter_keys as $key) {
				if (in_array($key, $seam_keys, true) || array_key_exists($key, $pair["omitted"])) {

					continue;
				}

				$missing[] = $key;
			}

			T::equals(
				implode(", ", $missing),
				"",
				"{$tool} returns every field its REST presenter does, or omits it on purpose"
			);
		}
	}

	function test_presenter_omissions_are_current_and_reasoned() {
		foreach (ai_presenter_pairs() as $tool => $pair) {
			$presenter_keys = [];

			foreach ($pair["presenter"][1] as $method) {
				$presenter_keys = array_merge(
					$presenter_keys,
					ai_presenter_keys($pair["presenter"][0], $method)
				);
			}

			$stale = array_values(array_diff(array_keys($pair["omitted"]), $presenter_keys));
			T::equals(
				implode(", ", $stale),
				"",
				"{$tool}'s omission list names no field the REST presenter stopped emitting"
			);

			foreach ($pair["omitted"] as $key => $reason) {
				T::ok(
					strlen(trim((string)$reason)) > 25,
					"{$tool} says why it omits {$key}"
				);
			}
		}
	}

	/**
	 * The widened read is only safe because the seam is gated. search_users is
	 * administrator-only at the tool, and the payload re-applies presentFull's own
	 * self-or-admin condition rather than trusting that.
	 */
	function test_widened_user_read_keeps_its_gate() {
		$tool = file_get_contents(SERVER_ROOT . "core/inc/bigtree/services/AI/Tools/SearchUsersTool.php");
		T::ok(
			strpos((string)$tool, 'CapabilitySummary::level($user) >= 1') !== false,
			"search_users is offered to administrators only"
		);
		T::ok(
			strpos((string)$tool, "userLevel() < 1") !== false,
			"and re-checks the level when it executes"
		);

		$seam = ai_presenter_keys(SearchService::class, "searchUsers");
		T::ok(in_array("permissions", $seam, true), "the seam returns permissions");
		T::ok(in_array("alerts", $seam, true), "the seam returns alert subscriptions");
	}
