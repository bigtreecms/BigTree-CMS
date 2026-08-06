<?php
	/**
	 * Audit #18: the turn's own resource envelope — the boundary on the *provider*
	 * side of AgentLoop, which audits #1–#17 never looked at.
	 *
	 * Every earlier guard relates a tool to a backend seam. None of these facts is
	 * about either one:
	 *
	 *   E1  the payload budget    — a read seam caps a value; nothing capped a
	 *                               payload, and nothing summed the payloads a turn
	 *                               accumulates and re-feeds on every round (A1).
	 *   E2  transport completeness — the provider's own "I stopped early" signal
	 *                               (finish_reason / stop_reason) appeared nowhere,
	 *                               so the buffered paths returned a cut-off turn as
	 *                               the authoritative answer (A2).
	 *   E3  no undeclared generation constant — max_tokens was a literal no setting
	 *                               could raise (A3).
	 *
	 * DB-free: the budget maths is pure, the provider paths are inspected as source
	 * or driven through a fake BigTreeAI, and the accumulator is fed captured lines.
	 * (chat_loop_registry() / ai_fake_user() / ai_surface_method_body() come from
	 * the other AI test files; all *Test.php load before any test_* runs.)
	 */

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolInterface;
	use BigTree\Services\AI\AIToolRegistry;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\AgentLoop;
	use BigTree\Services\AI\PayloadBudget;
	use BigTree\Services\AI\StreamAccumulator;
	use BigTree\Services\AI\TruncatedRead;
	use BigTree\Services\AI\TurnCompleteness;

	// — E1: the payload budget —

	/**
	 * Source with its comments removed.
	 *
	 * A guard that greps a method body for a call has to grep the code, not the
	 * prose: every seam here explains its budget in a comment above the call, and
	 * deleting the call while leaving the comment is exactly the change that must
	 * fail. (Verified by doing it.)
	 */
	function ai_code_without_comments(string $body): string {
		$code = "";

		foreach (token_get_all("<?php " . $body) as $token) {
			if (!is_array($token)) {
				$code .= $token;

				continue;
			}

			if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {

				continue;
			}

			$code .= $token[1];
		}

		return $code;
	}

	/** One class constant's value, private ones included. */
	function ai_service_constant(string $class, string $name): int {
		$constant = (new ReflectionClass($class))->getReflectionConstant($name);

		return $constant === false ? 0 : (int)$constant->getValue();
	}

	/**
	 * The row and field shapes the worst cases below are computed against. These are
	 * the only estimates here — everything else is read off the constants themselves,
	 * so a cap that moves moves the arithmetic with it.
	 *
	 * @return array<string,int>
	 */
	function ai_payload_shapes(): array {

		return [
			// A real module's readable schema. Nothing in the code bounds this, which
			// is the whole reason list_module_entries needs a runtime budget.
			"module_columns" => 25,
			// A content-heavy page template.
			"page_fields" => 10,
			// One get_page_tree child, JSON-encoded: eleven keys, a nav title and a
			// path (measured, not guessed — see the audit).
			"tree_row" => 232,
			// {"id":123,"name":"Marketing Assets"} and a little slack.
			"folder_row" => 60,
			// A relation candidate: an id and a label.
			"relation_row" => 90,
		];
	}

	/**
	 * Every AI read seam whose payload is a window over rows or fields, with the
	 * declared worst case its own constants imply.
	 *
	 * A seam passes by *either* being small enough to fit RESULT_CHARS on its
	 * declared numbers, or applying PayloadBudget at runtime. A seam that raises a
	 * row cap, drops a value cap or gains a dimension without doing one of the two
	 * fails here.
	 *
	 * `window` names the constant that sets the row count, and is what ties this map
	 * to the enumeration leg below: a new window constant must appear here, with its
	 * size stated, before the suite will pass.
	 *
	 * @return array<string,array{class:string,method:string,window:string,worst_case:int}>
	 */
	function ai_payload_budget_seams(): array {
		$shapes = ai_payload_shapes();
		$auto = \BigTree\Services\AutoModuleService::class;
		$pages = \BigTree\Services\PageService::class;

		return [
			// rows × columns × per-value cap
			"list_module_entries" => [
				"class" => $auto,
				"method" => "aiListEntries",
				"window" => $auto . "::AI_ENTRY_LIST_CAP",
				"worst_case" => ai_service_constant($auto, "AI_ENTRY_LIST_CAP")
					* $shapes["module_columns"]
					* \BigTree\Services\AutoModuleService::AI_ENTRY_LIST_VALUE_CAP,
			],
			// rows × one encoded child row
			"get_page_tree" => [
				"class" => $pages,
				"method" => "aiPageTree",
				"window" => $pages . "::AI_PAGE_TREE_CAP",
				"worst_case" => ai_service_constant($pages, "AI_PAGE_TREE_CAP") * $shapes["tree_row"],
			],
			// fields × per-field cap
			"get_page (content)" => [
				"class" => $pages,
				"method" => "aiPageContentFields",
				"window" => "",
				"worst_case" => $shapes["page_fields"] * \BigTree\Services\PageService::AI_CONTENT_FIELD_CAP,
			],
			// columns × per-column cap
			"get_module_entry" => [
				"class" => \BigTree\Services\SearchService::class,
				"method" => "getModuleEntryDetail",
				"window" => "",
				"worst_case" => $shapes["module_columns"] * \BigTree\Services\AutoModuleService::AI_ENTRY_READ_CAP,
			],
			// subfolders × one folder row (the file rows beside them are bounded by
			// AIToolContext::$limit, single digits, and are the smaller half)
			"list_resources" => [
				"class" => \BigTree\Services\ResourceService::class,
				"method" => "aiListResources",
				"window" => \BigTree\Services\ResourceService::class . "::AI_RESOURCE_FOLDER_CAP",
				"worst_case" => ai_service_constant(\BigTree\Services\ResourceService::class, "AI_RESOURCE_FOLDER_CAP")
					* $shapes["folder_row"],
			],
			// candidates × one id/label row
			"get_relation_options" => [
				"class" => $auto,
				"method" => "aiRelationOptions",
				"window" => $auto . "::AI_RELATION_OPTION_CAP",
				"worst_case" => ai_service_constant($auto, "AI_RELATION_OPTION_CAP") * $shapes["relation_row"],
			],
		];
	}

	/** E1: a seam over the ceiling on its own numbers must bound its payload at runtime. */
	function test_oversized_read_seams_apply_the_payload_budget() {
		// The two ways a seam bounds itself: drop rows, or read values shorter.
		$bounding_calls = ["PayloadBudget::fitRows(", "PayloadBudget::capForValues("];
		$unbounded = [];

		foreach (ai_payload_budget_seams() as $label => $seam) {
			$body = ai_surface_method_body($seam["class"], $seam["method"]);
			T::ok($body !== "", "{$label}: {$seam["method"]} source was read");
			T::ok($seam["worst_case"] > 0, "{$label}: its worst case is computed from real constants");

			if ($seam["worst_case"] <= PayloadBudget::RESULT_CHARS) {

				continue;
			}

			$code = ai_code_without_comments($body);
			$bounded = false;

			foreach ($bounding_calls as $call) {
				if (strpos($code, $call) !== false) {
					$bounded = true;
				}
			}

			if (!$bounded) {
				$unbounded[] = $label . " (worst case " . $seam["worst_case"] . " chars)";
			}
		}

		T::equals(
			implode(", ", $unbounded),
			"",
			"every read seam whose declared worst case exceeds RESULT_CHARS bounds its payload"
		);
	}

	/**
	 * What each integer `AI_*` constant across the services *is*, so the leg below
	 * can tell a window over a payload from a bound on something else.
	 *
	 * `row_window` is the only role that carries an obligation: the constant must
	 * also appear as a seam's `window` above, where its size is stated and checked.
	 * The rest are classified so that adding one is a deliberate act — the leg
	 * discovers constants by reflection, so a new cap fails until it is named here.
	 *
	 * @return array<string,array{0:string,1:string}> class::CONST => [role, why]
	 */
	function ai_window_constant_roles(): array {
		$auto = \BigTree\Services\AutoModuleService::class;
		$pages = \BigTree\Services\PageService::class;
		$resources = \BigTree\Services\ResourceService::class;
		$search = \BigTree\Services\SearchService::class;
		$callouts = \BigTree\Services\CalloutService::class;
		$modules = \BigTree\Services\ModuleService::class;
		$pending = \BigTree\Services\PendingChangeService::class;

		return [
			$auto . "::AI_ENTRY_LIST_CAP" => ["row_window", "list_module_entries' window"],
			$pages . "::AI_PAGE_TREE_CAP" => ["row_window", "get_page_tree's window"],
			$resources . "::AI_RESOURCE_FOLDER_CAP" => ["row_window", "list_resources' subfolder window"],
			$auto . "::AI_RELATION_OPTION_CAP" => ["row_window", "get_relation_options' window"],

			$auto . "::AI_ENTRY_LIST_VALUE_CAP" => ["value_cap", "per column in a list row"],
			$auto . "::AI_ENTRY_READ_CAP" => ["value_cap", "per column in one entry"],
			$pages . "::AI_CONTENT_FIELD_CAP" => ["value_cap", "per page content field"],
			$search . "::AI_SNIPPET_CAP" => ["value_cap", "per search-result snippet"],

			$resources . "::AI_FILE_SEARCH_SCAN_CAP" => ["scan_limit", "rows scanned before the folder filter, not returned"],
			$pending . "::AI_PENDING_SCAN_BATCH" => ["scan_limit", "batch size of a scan, not a payload"],

			$callouts . "::AI_CALLOUT_USAGE_LIMIT" => ["count_limit", "how far a COUNT scans; yields one 'at least N' sentence"],
			$pages . "::AI_INBOUND_LINK_LIMIT" => ["count_limit", "the same, for inbound page links"],
			$auto . "::AI_ENTRY_REFERENCE_LIMIT" => ["count_limit", "the same, for what points at an entry"],

			$search . "::AI_TOOL_LIMIT" => ["tool_window", "the row limit AIToolContext hands every list tool; each seam's own window is what bounds the payload"],
			$search . "::AI_MAX_ROUNDS" => ["not_a_size", "rounds in the search loop"],
			$search . "::AI_RATE_LIMIT" => ["not_a_size", "requests per window"],
			$search . "::AI_RATE_WINDOW" => ["not_a_size", "seconds in the rate window"],
			$callouts . "::AI_GROUP_NAME_MAX_LENGTH" => ["not_a_size", "input validation on a written value"],
			$modules . "::AI_GROUP_NAME_MAX_LENGTH" => ["not_a_size", "the same, for module groups"],
		];
	}

	/**
	 * Every integer `AI_*` constant a service declares, found by reflection rather
	 * than listed — which is what makes the leg below unable to rot quietly.
	 *
	 * @return array<string,int>
	 */
	function ai_declared_window_constants(): array {
		$found = [];

		foreach (glob(__DIR__ . "/../../services/*.php") ?: [] as $file) {
			$class = "BigTree\\Services\\" . basename($file, ".php");

			if (!class_exists($class)) {

				continue;
			}

			foreach ((new ReflectionClass($class))->getReflectionConstants() as $constant) {
				$name = $constant->getName();

				// Own constants only: an inherited one belongs to the class that
				// declares it, and would otherwise need an entry per subclass.
				if (strncmp($name, "AI_", 3) !== 0 || $constant->getDeclaringClass()->getName() !== $class) {

					continue;
				}

				if (!is_int($constant->getValue())) {

					continue;
				}

				$found[$class . "::" . $name] = (int)$constant->getValue();
			}
		}

		return $found;
	}

	/** E1: a new read window can't appear without saying how big its payload gets. */
	function test_every_ai_window_constant_is_classified() {
		$roles = ai_window_constant_roles();
		$declared = ai_declared_window_constants();
		T::ok(count($declared) >= 10, "the reflection sweep found the AI constants (" . count($declared) . ")");

		$unclassified = [];

		foreach (array_keys($declared) as $key) {
			if (!isset($roles[$key])) {
				$unclassified[] = $key;
			}
		}

		T::equals(
			implode(", ", $unclassified),
			"",
			"every AI_* size constant is classified — a new one is a deliberate act, not a silent window"
		);

		// The obligation half: a row window must be a seam with a stated size.
		$windows = [];

		foreach (ai_payload_budget_seams() as $seam) {
			if ($seam["window"] !== "") {
				$windows[$seam["window"]] = true;
			}
		}

		$uncovered = [];

		foreach ($roles as $key => [$role, $why]) {
			T::ok($why !== "", "{$key} says why it has the role it does");

			if ($role !== "row_window") {

				continue;
			}

			if (!isset($windows[$key])) {
				$uncovered[] = $key;
			}
		}

		T::equals(
			implode(", ", $uncovered),
			"",
			"every row window is a seam in the budget map, with its worst case computed"
		);

		// And nothing lingers in the classification that no longer exists.
		$stale = [];

		foreach (array_keys($roles) as $key) {
			if (!isset($declared[$key])) {
				$stale[] = $key;
			}
		}

		T::equals(implode(", ", $stale), "", "no classification entry outlives the constant it describes");
	}

	/** E1: the two ceilings are coherent, and one result always fits inside a turn. */
	function test_payload_budget_constants_are_coherent() {
		T::ok(PayloadBudget::RESULT_CHARS > 0, "a per-result ceiling is declared");
		T::ok(
			PayloadBudget::RESULT_CHARS <= PayloadBudget::TURN_CHARS,
			"one full-size result fits inside the per-turn budget"
		);

		// The second leg of the contract: a per-result ceiling cannot bound a turn on
		// its own, because MAX_TOOL_CALLS of them is an order of magnitude past any
		// context window. That gap is exactly what the running total in AgentLoop
		// exists to close — asserted behaviourally below.
		T::ok(
			AgentLoop::MAX_TOOL_CALLS * PayloadBudget::RESULT_CHARS > PayloadBudget::TURN_CHARS,
			"the tool-call cap alone leaves the turn unbounded, so a running total is required"
		);

		$ladder = PayloadBudget::VALUE_CAP_LADDER;
		T::ok(count($ladder) >= 2, "the value-cap ladder has steps to fall back through");

		$descending = true;

		for ($i = 1; $i < count($ladder); $i++) {
			if ($ladder[$i] >= $ladder[$i - 1]) {
				$descending = false;
			}
		}

		T::ok($descending, "the ladder descends");
	}

	/** E1: a budget-shortened value still ends at a cap TruncatedRead recognises. */
	function test_every_ladder_step_is_a_known_truncation_cap() {
		$caps = TruncatedRead::caps();
		$missing = [];

		foreach (PayloadBudget::VALUE_CAP_LADDER as $step) {
			if (!in_array($step, $caps, true)) {
				$missing[] = (string)$step;
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"TruncatedRead knows every cap a budget-constrained read can cut at"
		);
	}

	/** E1: rows are dropped before values, and the result fits. */
	function test_fit_rows_bounds_a_payload_and_keeps_whole_rows() {
		$row = [];

		foreach (range(1, 25) as $column) {
			$row["field_" . $column] = str_repeat("x", \BigTree\Services\AutoModuleService::AI_ENTRY_LIST_VALUE_CAP);
		}

		$rows = array_fill(0, 50, $row);
		T::ok(
			PayloadBudget::size($rows) > PayloadBudget::RESULT_CHARS,
			"the unbudgeted worst case really does exceed the ceiling"
		);

		$fitted = PayloadBudget::fitRows($rows);
		T::ok(count($fitted) >= 1, "at least one row survives — an empty window would read as an empty module");
		T::ok(count($fitted) < count($rows), "rows were dropped");
		T::ok(PayloadBudget::size($fitted) <= PayloadBudget::RESULT_CHARS, "what is kept fits the ceiling");
		T::ok($fitted[0] === $row, "the rows that are kept are kept whole");
	}

	/** E1: a small result is not touched. */
	function test_fit_rows_leaves_an_ordinary_result_alone() {
		$rows = array_fill(0, 10, ["id" => 1, "title" => "A news post", "body" => str_repeat("y", 200)]);
		T::equals(count(PayloadBudget::fitRows($rows)), 10, "an ordinary window is returned whole");
	}

	/** E1: a record with no rows to drop reads its values shorter, on the ladder. */
	function test_value_cap_falls_through_the_ladder() {
		$declared = \BigTree\Services\PageService::AI_CONTENT_FIELD_CAP;

		// The ordinary shape of a record: one long body beside a dozen short columns.
		// This is what audit #10 raised the cap for, and it must not be degraded by a
		// budget defending against a shape that doesn't occur.
		$ordinary = array_merge([$declared * 3], array_fill(0, 24, 40));
		T::equals(
			PayloadBudget::capForValues($ordinary, $declared),
			$declared,
			"a 25-column entry with one long body still reads at the full declared cap"
		);

		// Twenty long fields genuinely don't fit.
		$fat = array_fill(0, 20, $declared * 2);
		$cap = PayloadBudget::capForValues($fat, $declared);
		T::ok($cap < $declared, "a record of twenty long fields reads shorter");
		T::ok(in_array($cap, PayloadBudget::VALUE_CAP_LADDER, true), "and it lands on a ladder step");
		T::ok($cap * 20 <= PayloadBudget::RESULT_CHARS, "so the record then fits the ceiling");

		// Never above the seam's own cap, however much budget is spare.
		T::equals(
			PayloadBudget::capForValues([10], \BigTree\Services\AutoModuleService::AI_ENTRY_LIST_VALUE_CAP),
			\BigTree\Services\AutoModuleService::AI_ENTRY_LIST_VALUE_CAP,
			"the budget is a ceiling, never a promotion"
		);

		// A pathological record lands on the last step rather than on nothing.
		T::ok(
			PayloadBudget::capForValues(array_fill(0, 5000, $declared), $declared) > 0,
			"a pathological record still shows something of every field"
		);
	}

	if (!class_exists("FatReadTool")) {
		/**
		 * A read tool whose result is deliberately enormous, so the per-turn running
		 * total can be exercised without a database.
		 */
		class FatReadTool implements AIToolInterface {
			/** @var int */
			public $chars;

			public function __construct(int $chars) {
				$this->chars = $chars;
			}

			public function name(): string {

				return "fat_read";
			}

			public function kind(): string {

				return "read";
			}

			public function isAvailable($user): bool {

				return true;
			}

			public function definition($user): array {

				return [
					"type" => "function",
					"function" => [
						"name" => $this->name(),
						"description" => "Returns a very large payload.",
						"parameters" => ["type" => "object", "properties" => []],
					],
				];
			}

			public function execute(array $args, AIToolContext $context): AIToolResult {

				return AIToolResult::ok(["blob" => str_repeat("z", $this->chars)]);
			}
		}
	}

	/** E1: a turn that reads past TURN_CHARS is refused in the model's own terms. */
	function test_agent_loop_stops_reading_at_the_turn_budget() {
		// Just inside the per-result ceiling, so each read is one the backstop passes
		// through whole — this leg is about their *sum*, and a result the backstop
		// replaced would never reach it.
		$per_call = PayloadBudget::RESULT_CHARS - 100;
		$registry = new AIToolRegistry();
		$registry->register(new FatReadTool($per_call));

		$calls = [];
		$call_count = (int)ceil(PayloadBudget::TURN_CHARS / $per_call) + 2;

		// One round, every call in parallel: the leading ones fit the turn's reading
		// budget, and the rest must come back as the recoverable refusal rather than
		// as executed reads.
		foreach (range(1, $call_count) as $i) {
			$calls[] = [
				"id" => "c" . $i,
				"type" => "function",
				"function" => ["name" => "fat_read", "arguments" => "{}"],
			];
		}

		$ai = new FakeChatAI([
			[
				"content" => null,
				"tool_calls" => array_map(function (array $call): array {

					return ["id" => $call["id"], "name" => "fat_read", "arguments" => []];
				}, $calls),
				"raw" => ["_openai_tool_calls" => $calls],
			],
			fake_answer_response("Here is what I found."),
		]);

		$loop = new AgentLoop($ai, $registry, 4);
		$run = $loop->run([
			["role" => "system", "content" => "sys"],
			["role" => "user", "content" => "read everything"],
		], new AIToolContext(ai_fake_user(0), 8));

		$statuses = array_map(function (array $activity): string {

			return (string)$activity["status"];
		}, $run["tool_activity"]);

		T::equals(count($statuses), $call_count, "every call the model made is recorded");
		T::equals($statuses[0], AIToolResult::OK, "reads run while there is budget");
		T::equals($statuses[$call_count - 1], AIToolResult::ERROR, "reads past the budget are refused");
		T::ok(
			in_array(AIToolResult::ERROR, $statuses, true) && in_array(AIToolResult::OK, $statuses, true),
			"the budget cuts the turn off part-way, rather than all-or-nothing"
		);

		// A refusal, not a provider failure: the turn still answers.
		T::equals($run["error"], null, "the turn survives its reading budget");
		T::equals($run["answer"], "Here is what I found.", "and answers from what it has");

		$refusal = "";

		foreach ($run["messages"] as $message) {
			if (($message["role"] ?? "") === "tool" && strpos((string)$message["content"], "reading budget") !== false) {
				$refusal = (string)$message["content"];
			}
		}

		T::ok($refusal !== "", "the model is told, in words, that the turn is out of reading budget");
	}

	/**
	 * E1: the backstop. A seam that never learned to bound itself is refused at the
	 * loop rather than sent to the provider — this is what makes RESULT_CHARS an
	 * invariant of the request instead of a convention each seam opts into.
	 */
	function test_agent_loop_refuses_a_single_oversized_result() {
		$registry = new AIToolRegistry();
		$registry->register(new FatReadTool(PayloadBudget::RESULT_CHARS * 2));

		$call = [
			"id" => "c1",
			"type" => "function",
			"function" => ["name" => "fat_read", "arguments" => "{}"],
		];
		$ai = new FakeChatAI([
			[
				"content" => null,
				"tool_calls" => [["id" => "c1", "name" => "fat_read", "arguments" => []]],
				"raw" => ["_openai_tool_calls" => [$call]],
			],
			fake_answer_response("I could not read that."),
		]);

		$loop = new AgentLoop($ai, $registry, 3);
		$run = $loop->run([
			["role" => "system", "content" => "sys"],
			["role" => "user", "content" => "read the big thing"],
		], new AIToolContext(ai_fake_user(0), 8));

		$tool_message = "";

		foreach ($run["messages"] as $message) {
			if (($message["role"] ?? "") === "tool") {
				$tool_message = (string)$message["content"];
			}
		}

		T::ok($tool_message !== "", "the tool result reached the model as a message");
		T::ok(
			mb_strlen($tool_message) < PayloadBudget::RESULT_CHARS,
			"an oversized result never enters the request"
		);
		T::ok(strpos($tool_message, "zzzz") === false, "and none of the payload is smuggled through in part");
		T::ok(
			strpos($tool_message, "too large to read") !== false || strpos($tool_message, "characters") !== false,
			"the model is told what happened in terms it can act on"
		);
		T::ok(strpos($tool_message, "fat_read") !== false, "including which tool it was");

		// The run itself is unharmed: a refused read is recoverable, like any other.
		T::equals($run["error"], null, "an oversized read does not fail the turn");
		T::equals($run["answer"], "I could not read that.", "the turn still answers");
		T::equals(
			(string)$run["tool_activity"][0]["status"],
			AIToolResult::OK,
			"the tool itself ran and is recorded as it ran — only what the model is shown is replaced"
		);
	}

	/** E1: a staged proposal is never reported to the model as an error, whatever its size. */
	function test_the_backstop_leaves_a_proposal_alone() {
		$method = new ReflectionMethod(AgentLoop::class, "budgetedPayload");
		$method->setAccessible(true);

		$fat = str_repeat("p", PayloadBudget::RESULT_CHARS * 2);
		$proposal = AIToolResult::proposal("Rename page 42.", ["nav_title" => $fat], "prop-1");
		$payload = $method->invoke(null, $proposal, "update_page");

		T::equals(
			(string)$payload["status"],
			AIToolResult::PROPOSAL,
			"an oversized proposal is still a proposal — telling the model a staged card failed invites a duplicate"
		);
		T::equals((string)$payload["proposal_id"], "prop-1", "and it keeps the id the user will approve");

		$ok = AIToolResult::ok(["blob" => $fat]);
		T::equals(
			(string)$method->invoke(null, $ok, "fat_read")["status"],
			AIToolResult::ERROR,
			"while an oversized read is refused"
		);
	}

	// — E2: transport completeness —

	/**
	 * Every path that turns a provider response into a turn, mapped to the substring
	 * proving it consults a completeness signal before returning one.
	 *
	 * @return array<string,array{0:string,1:string,2:string}> label => [class, method, proof]
	 */
	function ai_completeness_paths(): array {

		return [
			"buffered openai/xai" => [\BigTreeAI::class, "chatOpenAICompatible", "TurnCompleteness::stopSignal"],
			"buffered anthropic" => [\BigTreeAI::class, "chatAnthropic", "TurnCompleteness::stopSignal"],
			"streaming transport" => [\BigTreeAI::class, "chatStream", "isComplete()"],
			"streaming assembly" => [StreamAccumulator::class, "result", "TurnCompleteness::isLength"],
		];
	}

	/**
	 * Provider methods on BigTreeAI that are *not* response paths, with why. Keeps
	 * the enumeration leg below honest: a fourth provider path cannot be added
	 * without either checking completeness or being named here.
	 *
	 * Only genuine non-paths belong here. Listing a checked path as well would make
	 * the leg self-defeating — deleting its entry from ai_completeness_paths() would
	 * then leave it exempt rather than failing, which is the one thing this leg is
	 * for.
	 *
	 * @return array<string,string>
	 */
	function ai_completeness_non_paths(): array {

		return [
			"chat" => "dispatcher: picks a provider path, never reads a response itself",
		];
	}

	/** E2: every response path consults a completeness signal. */
	function test_every_provider_path_checks_completeness() {
		$missing = [];

		foreach (ai_completeness_paths() as $label => [$class, $method, $proof]) {
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$label}: {$method} source was read");

			if (strpos($body, $proof) === false) {
				$missing[] = $label;
			}
		}

		T::equals(implode(", ", $missing), "", "every provider response path checks whether the turn finished");
	}

	/** E2: a new provider path can't be added unchecked. */
	function test_no_unenumerated_provider_path() {
		$checked = [];

		foreach (ai_completeness_paths() as [$class, $method, $proof]) {
			if ($class === \BigTreeAI::class) {
				$checked[$method] = true;
			}
		}

		$exempt = ai_completeness_non_paths();

		foreach (array_keys($exempt) as $name) {
			T::ok(
				!isset($checked[$name]),
				"{$name} is exempt or checked, never both — an entry in each map would exempt a real path"
			);
		}

		$unknown = [];

		foreach ((new ReflectionClass(\BigTreeAI::class))->getMethods() as $method) {
			$name = $method->getName();

			if (strncmp($name, "chat", 4) !== 0) {

				continue;
			}

			if (isset($checked[$name]) || array_key_exists($name, $exempt)) {

				continue;
			}

			$unknown[] = $name;
		}

		T::equals(implode(", ", $unknown), "", "no provider chat path escapes the completeness map");
	}

	/** E2: both providers' stop signals are read from where they actually live. */
	function test_stop_signal_is_read_per_provider() {
		T::equals(
			TurnCompleteness::stopSignal("openai", ["choices" => [["finish_reason" => "length"]]]),
			"length",
			"openai's finish_reason is read"
		);
		T::equals(
			TurnCompleteness::stopSignal("xai", ["choices" => [["finish_reason" => "stop"]]]),
			"stop",
			"xai shares the chat-completions shape"
		);
		T::equals(
			TurnCompleteness::stopSignal("anthropic", ["stop_reason" => "max_tokens"]),
			"max_tokens",
			"anthropic's stop_reason is read"
		);
		T::equals(TurnCompleteness::stopSignal("openai", []), "", "a response with no signal reads as empty");

		T::ok(TurnCompleteness::isLength("length"), "openai's ceiling stop is recognised");
		T::ok(TurnCompleteness::isLength("max_tokens"), "anthropic's ceiling stop is recognised");
		T::ok(!TurnCompleteness::isLength("stop"), "a normal stop is not a truncation");
		T::ok(!TurnCompleteness::isLength("tool_calls"), "a tool-call stop is not a truncation");
	}

	/** E2: a truncated answer says so rather than ending mid-sentence. */
	function test_truncated_answer_is_marked() {
		$marked = TurnCompleteness::markTruncatedAnswer("The first three steps are");
		T::ok(strpos($marked, "The first three steps are") === 0, "the partial answer is kept");
		T::ok(
			strpos($marked, TurnCompleteness::TRUNCATED_ANSWER_NOTE) !== false,
			"and carries the note that it was cut off"
		);
		T::equals(
			TurnCompleteness::markTruncatedAnswer(null),
			TurnCompleteness::TRUNCATED_ANSWER_NOTE,
			"an answer that never started is still explained, not blank"
		);
	}

	/** E2: a stream cut at the token ceiling mid-tool-call refuses the turn. */
	function test_stream_length_stop_with_tool_calls_is_incomplete() {
		$acc = new StreamAccumulator("openai", function (string $c): void {});
		$acc->feedLine('data: {"choices":[{"delta":{"tool_calls":[{"index":0,"id":"c1","function":{"name":"update_page","arguments":"{\"id\":4}"}}]}}]}');
		$acc->feedLine('data: {"choices":[{"delta":{},"finish_reason":"length"}]}');
		$acc->feedLine("data: [DONE]");

		$acc->result();
		T::ok(!$acc->isComplete(), "a length-stopped tool call is not a turn we can run");
		T::equals(
			$acc->incompleteReason(),
			TurnCompleteness::TRUNCATED_TOOL_CALL,
			"and it refuses in the same words the buffered paths use"
		);
	}

	/** E2: a stream cut at the token ceiling mid-answer keeps the answer and marks it. */
	function test_stream_length_stop_marks_the_answer() {
		$acc = new StreamAccumulator("anthropic", function (string $c): void {});
		$acc->feedLine('data: {"type":"content_block_start","index":0,"content_block":{"type":"text"}}');
		$acc->feedLine('data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Here are the first"}}');
		$acc->feedLine('data: {"type":"message_delta","delta":{"stop_reason":"max_tokens"}}');
		$acc->feedLine('data: {"type":"message_stop"}');

		$result = $acc->result();
		T::ok(strpos((string)$result["content"], "Here are the first") === 0, "the partial answer survives");
		T::ok(
			strpos((string)$result["content"], TurnCompleteness::TRUNCATED_ANSWER_NOTE) !== false,
			"and says it was cut off"
		);
		T::ok($acc->isComplete(), "a truncated answer is deliverable — only a truncated tool call is not");
	}

	/** E2: the buffered paths refuse exactly the turns they cannot honestly run. */
	function test_buffered_turn_refusal_decision() {
		$ai = new BigTreeAI();
		$method = new ReflectionMethod(BigTreeAI::class, "isTruncatedTurn");
		$method->setAccessible(true);
		$calls = [["id" => "c1", "name" => "update_page", "arguments" => ["id" => 4]]];

		T::ok(
			$method->invoke($ai, "length", $calls, false),
			"a turn stopped at the token ceiling with tool calls in flight is refused"
		);
		T::ok(
			$method->invoke($ai, "tool_calls", $calls, true),
			"an argument blob that never parsed is refused whatever the stop reason"
		);
		T::ok(
			!$method->invoke($ai, "length", [], false),
			"a length-stopped answer with no tool calls is kept and marked, not refused"
		);
		T::ok(
			!$method->invoke($ai, "tool_calls", $calls, false),
			"an ordinary tool-calling turn is untouched"
		);
	}

	/** E2: an ordinary stream is unaffected by the new signal. */
	function test_normal_stream_is_not_marked() {
		$acc = new StreamAccumulator("openai", function (string $c): void {});
		$acc->feedLine('data: {"choices":[{"delta":{"content":"All done."}}]}');
		$acc->feedLine('data: {"choices":[{"delta":{},"finish_reason":"stop"}]}');
		$acc->feedLine("data: [DONE]");

		$result = $acc->result();
		T::equals($result["content"], "All done.", "a complete answer is returned untouched");
		T::ok($acc->isComplete(), "and the turn is complete");
	}

	// — E3: no undeclared generation constant —

	/**
	 * Files that assemble a provider request, and the generation parameters a
	 * hard-coded number in one of them would silently become policy for.
	 *
	 * @return list<string>
	 */
	function ai_generation_budget_files(): array {

		return [
			__DIR__ . "/../../services/AI/AgentLoop.php",
			__DIR__ . "/../../services/AIChatService.php",
			__DIR__ . "/../../services/SearchService.php",
			__DIR__ . "/../../apis/ai.php",
		];
	}

	/**
	 * Numeric generation-parameter literals that are deliberate, each with the
	 * reason it isn't a setting. Anything else must come from the settings surface
	 * (BigTreeAI::maxTokens / finalMaxTokens) or a named default constant.
	 *
	 * @return array<string,string> "parameter=value" => reason
	 */
	function ai_generation_constant_exemptions(): array {

		return [
			"temperature=0.2" => "search: a deterministic lookup loop, not a style choice the site should tune",
			"temperature=0.3" => "chat: the same, marginally warmer for conversational phrasing",
			// The settings *record's* own seed, not a request parameter: 0 is how the
			// stored setting says "follow the provider default" (BigTreeAI::maxTokens).
			"max_tokens=0" => "ai.php: the unconfigured settings seed, read as 'use the default'",
			"final_max_tokens=0" => "ai.php: the same seed for the final-answer budget",
		];
	}

	/** E3: catches the next hard-coded max_tokens. */
	function test_no_undeclared_generation_constant() {
		$exemptions = ai_generation_constant_exemptions();
		$undeclared = [];

		foreach (ai_generation_budget_files() as $file) {
			T::ok(is_readable($file), basename($file) . " was read");
			$source = (string)file_get_contents($file);
			$matches = [];
			preg_match_all(
				'/"(max_tokens|final_max_tokens|temperature)"\s*=>\s*([^,\n]+)/',
				$source,
				$matches,
				PREG_SET_ORDER
			);

			foreach ($matches as $match) {
				$parameter = $match[1];
				$expression = trim($match[2]);

				// An expression (a setting, a constant, a passed-through option) is
				// exactly what this guard wants to see.
				if (!preg_match('/^\(?(int|float)?\)?\s*[\d.]+$/', $expression)) {

					continue;
				}

				$key = $parameter . "=" . ltrim(preg_replace('/^\((int|float)\)/', "", $expression) ?? $expression);

				if (isset($exemptions[$key])) {

					continue;
				}

				$undeclared[] = basename($file) . ": " . $key;
			}
		}

		T::equals(
			implode(", ", $undeclared),
			"",
			"every generation parameter is configurable or a declared, reasoned constant"
		);
	}

	/** E3: the budget really is reachable from the settings record. */
	function test_generation_budget_is_settable() {
		$ai = new BigTreeAI();

		$ai->Settings = ["service" => "openai"];
		T::equals($ai->maxTokens(), BigTreeAI::DEFAULT_MAX_TOKENS, "an unset budget falls back to the default");
		T::equals(
			$ai->finalMaxTokens(),
			BigTreeAI::DEFAULT_FINAL_MAX_TOKENS,
			"and so does the final-answer budget"
		);

		$ai->Service = "anthropic";
		$ai->Settings = ["service" => "anthropic"];
		T::equals(
			$ai->maxTokens(),
			BigTreeAI::SERVICE_TOKEN_DEFAULTS["anthropic"]["max"],
			"a provider with its own default gets it"
		);

		$ai->Settings = ["service" => "anthropic", "max_tokens" => 12000, "final_max_tokens" => 6000];
		T::equals($ai->maxTokens(), 12000, "a configured budget is used");
		T::equals($ai->finalMaxTokens(), 6000, "including for the final answer");

		$ai->Settings = ["service" => "anthropic", "max_tokens" => 5];
		T::equals($ai->maxTokens(), BigTreeAI::MIN_TOKENS, "a value below the floor is clamped up");

		$ai->Settings = ["service" => "anthropic", "max_tokens" => 999999];
		T::equals($ai->maxTokens(), BigTreeAI::MAX_TOKENS_CEILING, "a value above the ceiling is clamped down");
	}

	/** E3: the round budget is no longer the 1024 that made authoring impossible. */
	function test_default_round_budget_can_carry_a_page_body() {
		T::ok(
			BigTreeAI::DEFAULT_MAX_TOKENS >= 4096,
			"a tool-calling round can carry a real page body in its arguments"
		);
		T::ok(
			BigTreeAI::DEFAULT_FINAL_MAX_TOKENS >= 1024,
			"and a final answer isn't cut off at a paragraph"
		);
	}
