<?php
	/**
	 * Audit #9 Part A: the sequencing boundary.
	 *
	 * Audits #1–#8 hardened one proposal resolving to one approval. None of them
	 * examined the case where the user's intent needs two: "create a callout group
	 * and put this callout in it", "create the News module and add the first entry",
	 * "create the pricing page and redirect the old URL to it". Every validate seam
	 * reads live state, and nothing has been written while the first card is still on
	 * screen — so the second call either dead-ends against a world where step one
	 * hasn't happened, or stages successfully against a value step one will change.
	 *
	 * These are the guards for the narrow fix (decision D6): a `needs_prior_change`
	 * result that names a *real* pending proposal, a dependency recorded on the card
	 * that does stage, and an approval that refuses an unmet prerequisite.
	 *
	 * Pure: a fake store holds the pending rows, so no DB and no provider is touched.
	 * (ai_fake_user comes from AIToolFrameworkTest; every *Test.php is required
	 * before any test_* runs.)
	 */

	use BigTree\Services\AIChatService;
	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;
	use BigTree\Services\AI\Tools\AbstractMutatingTool;
	use BigTree\Api\Exceptions\BadRequestException;

	if (!class_exists("SequencingProposalStore")) {
		/**
		 * A store with a scripted set of pending rows. Rows are shaped exactly as the
		 * real table stores them — preview and payload as JSON strings — so the
		 * matching runs against production's data shape rather than a friendlier one.
		 */
		class SequencingProposalStore extends ProposalStore {
			/** @var list<array<string,mixed>> */
			public $pending = [];

			/** @var list<array<string,mixed>> */
			public $created = [];

			public function addPending(string $id, string $tool, string $summary, array $payload, array $preview = []): void {
				$this->pending[] = [
					"id" => $id,
					"conversation" => 5,
					"tool" => $tool,
					"summary" => $summary,
					"preview" => json_encode($preview),
					"payload" => json_encode($payload),
					"status" => self::PENDING,
				];
			}

			public function pendingForConversation(int $conversation_id, $tools = null): array {
				$tools = $tools === null ? [] : (is_array($tools) ? $tools : [$tools]);

				return array_values(array_filter($this->pending, function (array $row) use ($conversation_id, $tools): bool {

					return (int)$row["conversation"] === $conversation_id
						&& (!$tools || in_array($row["tool"], $tools, true));
				}));
			}

			public function create($user, int $conversation_id, string $tool, string $summary, array $preview, array $payload): array {
				$row = [
					"id" => "prop-seq-" . (count($this->created) + 1),
					"conversation" => $conversation_id,
					"tool" => $tool,
					"summary" => $summary,
					"preview" => $preview,
					"payload" => $payload,
					"status" => self::PENDING,
				];
				$this->created[] = $row;

				return $row;
			}

			/** Used by the approval guard; returns a scripted row by id. */
			public function loadOwned(string $id, $user): ?array {
				foreach ($this->pending as $row) {
					if ($row["id"] === $id) {

						return $row;
					}
				}

				return null;
			}
		}

		/**
		 * A mutating tool that returns whatever validation the test scripts. The
		 * sequencing logic lives in AbstractMutatingTool::stageFromValidation, shared
		 * by the whole catalog, so exercising it directly is exercising every tool's
		 * version of it at once.
		 */
		class SequencingTool extends AbstractMutatingTool {
			/** @var array<string,mixed> */
			public $validation = [];

			public function name(): string {

				return "sequencing_probe";
			}

			public function definition($user): array {

				return $this->functionDefinition($this->name(), "Test probe.", ["type" => "object", "properties" => []]);
			}

			public function execute(array $args, AIToolContext $context): AIToolResult {

				return $this->stageFromValidation($this->validation, $context, $this->name());
			}
		}
	}

	/** The store + tool pair every test below starts from. */
	function ai_sequencing_pair(): array {
		$store = new SequencingProposalStore();
		$tool = new SequencingTool($store);

		return [$store, $tool];
	}

	/** A validation that can't proceed without a container that doesn't exist. */
	function ai_sequencing_blocked_validation(): array {

		return [
			"needs_input" => [
				"question" => "There's no callout group called “Promos”. Which group should this callout go in?",
				"options" => [["id" => "sidebar", "label" => "Sidebar"]],
			],
			"prior_change" => [
				"tool" => "create_callout_group",
				"value" => "Promos",
				"keys" => ["name"],
				"label" => "A callout group called “Promos”",
			],
		];
	}

	/**
	 * With nothing staged, the seam's own answer stands: the model is asked which of
	 * the existing groups to use. needs_prior_change must never be invented.
	 */
	function test_needs_prior_change_requires_a_real_pending_proposal() {
		[, $tool] = ai_sequencing_pair();
		$tool->validation = ai_sequencing_blocked_validation();

		$result = $tool->execute([], new AIToolContext(ai_fake_user(2), 8, "5"));

		T::equals($result->type, AIToolResult::NEEDS_INPUT, "with no pending proposal the seam's needs_input stands");
	}

	/** And with the group staged, the answer becomes "approve that card first". */
	function test_needs_prior_change_names_the_pending_proposal() {
		[$store, $tool] = ai_sequencing_pair();
		$store->addPending("prop-group-1", "create_callout_group", "Create a new callout group “Promos”.", ["name" => "Promos"]);
		$tool->validation = ai_sequencing_blocked_validation();

		$result = $tool->execute([], new AIToolContext(ai_fake_user(2), 8, "5"));

		T::equals($result->type, AIToolResult::NEEDS_PRIOR_CHANGE, "a staged prerequisite changes the answer");
		T::equals($result->proposal_id, "prop-group-1", "the result names the proposal to approve");
		T::equals($result->prior_tool, "create_callout_group", "and the tool that staged it");

		$payload = $result->toModelPayload();
		T::equals($payload["status"], "needs_prior_change", "the model sees a distinguishable status");
		T::ok(
			strpos($payload["message"], "doesn't exist yet") !== false,
			"the message says the thing doesn't exist yet"
		);
		T::ok(
			strpos($payload["message"], "nothing has been written") !== false,
			"and that nothing has been written"
		);
		T::equals($payload["pending_proposal_id"], "prop-group-1", "the model is given the proposal id");
	}

	/** A pending proposal for something else entirely is not a prerequisite. */
	function test_a_different_pending_proposal_is_not_a_prerequisite() {
		[$store, $tool] = ai_sequencing_pair();
		$store->addPending("prop-group-9", "create_callout_group", "Create a new callout group “Sidebar”.", ["name" => "Sidebar"]);
		$tool->validation = ai_sequencing_blocked_validation();

		$result = $tool->execute([], new AIToolContext(ai_fake_user(2), 8, "5"));

		T::equals($result->type, AIToolResult::NEEDS_INPUT, "a pending group by another name doesn't match");
	}

	/** Matching ignores case and surrounding slashes — "/pricing" is "pricing". */
	function test_prior_change_matching_normalizes_case_and_slashes() {
		[$store, $tool] = ai_sequencing_pair();
		$store->addPending(
			"prop-page-1",
			"create_page",
			"Create page “Pricing” under the site root.",
			["route" => "pricing"],
			["path" => "/pricing"]
		);
		$tool->validation = [
			"needs_input" => ["question" => "?", "options" => []],
			"prior_change" => [
				"tool" => "create_page",
				"value" => "PRICING/",
				"keys" => ["path", "route"],
				"label" => "The page at /pricing",
			],
		];

		$result = $tool->execute([], new AIToolContext(ai_fake_user(2), 8, "5"));

		T::equals($result->type, AIToolResult::NEEDS_PRIOR_CHANGE, "case and slashes don't defeat the match");
		T::equals($result->proposal_id, "prop-page-1", "and the right proposal is named");
	}

	/**
	 * The non-blocking half: a redirect to a page that isn't created yet is
	 * legitimate, so it stages — but the dependency is recorded on the card and in
	 * the payload, because only the approval order is in question.
	 */
	function test_a_non_blocking_prerequisite_stages_with_a_dependency() {
		[$store, $tool] = ai_sequencing_pair();
		$store->addPending("prop-page-2", "create_page", "Create page “Pricing” under the site root.", ["route" => "pricing"]);
		$tool->validation = [
			"ok" => true,
			"summary" => "Redirect /old-pricing to /pricing.",
			"preview" => ["action" => "create_redirect"],
			"payload" => ["from" => "/old-pricing", "to" => "/pricing"],
			"prior_change" => [
				"tool" => "create_page",
				"value" => "pricing",
				"keys" => ["route"],
				"label" => "The page at /pricing",
				"blocking" => false,
			],
		];

		$result = $tool->execute([], new AIToolContext(ai_fake_user(2), 8, "5"));

		T::equals($result->type, AIToolResult::PROPOSAL, "a non-blocking prerequisite still stages");
		T::equals(count($store->created), 1, "exactly one proposal was staged");

		$staged = $store->created[0];
		$depends = $staged["payload"][AbstractMutatingTool::DEPENDS_ON_KEY] ?? null;

		T::ok(is_array($depends), "the payload records the prerequisite");
		T::equals($depends["id"], "prop-page-2", "naming the proposal it depends on");
		T::ok(
			isset($staged["preview"]["depends_on"]) && $staged["preview"]["depends_on"] !== "",
			"and the card says so, so the user knows which to approve first"
		);
		T::ok(
			strpos($staged["summary"], "Approve that one first") !== false,
			"the summary carries it too, for the model reading the tool result"
		);
	}

	/** A stateless turn (the search loop) has no conversation, so nothing to match. */
	function test_no_conversation_means_no_prior_change() {
		[$store, $tool] = ai_sequencing_pair();
		$store->addPending("prop-group-1", "create_callout_group", "Create a new callout group “Promos”.", ["name" => "Promos"]);
		$tool->validation = ai_sequencing_blocked_validation();

		$result = $tool->execute([], new AIToolContext(ai_fake_user(2), 8, ""));

		T::equals($result->type, AIToolResult::NEEDS_INPUT, "with no conversation the seam's own answer stands");
	}

	/**
	 * The approval half of A3: a card whose prerequisite is still pending refuses,
	 * naming it — before the claim, so it stays approvable once the other one lands.
	 */
	function test_approval_refuses_an_unmet_prerequisite() {
		$store = new SequencingProposalStore();
		$store->addPending("prop-page-3", "create_page", "Create page “Pricing” under the site root.", []);

		$service = new AIChatService();
		$assert = new ReflectionMethod($service, "assertPrerequisiteResolved");
		$assert->setAccessible(true);

		$payload = [AbstractMutatingTool::DEPENDS_ON_KEY => [
			"id" => "prop-page-3",
			"tool" => "create_page",
			"summary" => "Create page “Pricing” under the site root.",
		]];

		T::throws(function () use ($assert, $service, $store, $payload) {
			$assert->invoke($service, $store, $payload, ai_fake_user(2));
		}, BadRequestException::class, "approving out of order is refused");

		try {
			$assert->invoke($service, $store, $payload, ai_fake_user(2));
		} catch (BadRequestException $e) {
			T::ok(
				strpos($e->getMessage(), "Pricing") !== false,
				"and the refusal names the card to approve first"
			);
		}
	}

	/**
	 * A prerequisite whose own approval ran and refused is equally unmet — it is
	 * still a live, retryable card, and what it would create still doesn't exist.
	 */
	function test_approval_refuses_a_failed_prerequisite() {
		$store = new SequencingProposalStore();
		$store->addPending("prop-page-4", "create_page", "Create page “Pricing” under the site root.", []);
		$store->pending[0]["status"] = ProposalStore::FAILED;

		$service = new AIChatService();
		$assert = new ReflectionMethod($service, "assertPrerequisiteResolved");
		$assert->setAccessible(true);

		T::throws(function () use ($assert, $service, $store) {
			$assert->invoke($service, $store, [AbstractMutatingTool::DEPENDS_ON_KEY => [
				"id" => "prop-page-4",
				"tool" => "create_page",
				"summary" => "Create page “Pricing” under the site root.",
			]], ai_fake_user(2));
		}, BadRequestException::class, "a failed prerequisite blocks too — nothing was written");
	}

	/** A rejected one does not: the user decided against it. */
	function test_a_rejected_prerequisite_does_not_block() {
		$store = new SequencingProposalStore();
		$store->addPending("prop-page-5", "create_page", "Create page “Pricing” under the site root.", []);
		$store->pending[0]["status"] = ProposalStore::REJECTED;

		$service = new AIChatService();
		$assert = new ReflectionMethod($service, "assertPrerequisiteResolved");
		$assert->setAccessible(true);

		$assert->invoke($service, $store, [AbstractMutatingTool::DEPENDS_ON_KEY => [
			"id" => "prop-page-5",
			"tool" => "create_page",
			"summary" => "Create page “Pricing” under the site root.",
		]], ai_fake_user(2));

		T::ok(true, "a rejected prerequisite leaves the decision to the approval-time re-checks");
	}

	/** Once the prerequisite is resolved (or was never staged), approval proceeds. */
	function test_approval_proceeds_when_the_prerequisite_is_gone() {
		$store = new SequencingProposalStore();
		$service = new AIChatService();
		$assert = new ReflectionMethod($service, "assertPrerequisiteResolved");
		$assert->setAccessible(true);

		// Not in the store at all: approved, rejected or expired away. The container
		// re-checks at approval are what catch a prerequisite that was rejected.
		$assert->invoke($service, $store, [AbstractMutatingTool::DEPENDS_ON_KEY => [
			"id" => "prop-gone",
			"tool" => "create_page",
			"summary" => "Create page “Pricing”.",
		]], ai_fake_user(2));

		// And a payload with no dependency at all is the ordinary case.
		$assert->invoke($service, $store, ["id" => 4], ai_fake_user(2));

		T::ok(true, "a resolved or absent prerequisite doesn't block approval");
	}

	/**
	 * The reserved payload keys never reach an execute seam — a seam that saw
	 * __depends_on__ in its payload would have to know about it, which is exactly
	 * what the fingerprint and lock keys are stripped to avoid.
	 */
	function test_the_dependency_key_is_reserved_and_stripped() {
		$source = file_get_contents(SERVER_ROOT . "core/inc/bigtree/services/AIChatService.php");

		T::ok(
			strpos((string)$source, "AbstractMutatingTool::DEPENDS_ON_KEY]") !== false,
			"approveProposal unsets the dependency key before dispatching"
		);
		T::ok(
			strpos(AbstractMutatingTool::DEPENDS_ON_KEY, "__") === 0,
			"and it is namespaced like the other reserved keys"
		);
	}

	/**
	 * The seams that can only say "that doesn't exist" are the ones a two-step intent
	 * dies at. Each must emit the descriptor that lets the staging layer tell "you
	 * invented it" from "it's on the card you just showed the user".
	 *
	 * Structural, like the fingerprint and lock guards: it proves the seam offers the
	 * hint at all, not that the wording is right.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	function ai_sequencing_hinting_seams(): array {

		return [
			"callout group" => [\BigTree\Services\CalloutService::class, "aiResolveCalloutGroup"],
			"module group" => [\BigTree\Services\ModuleService::class, "aiResolveModuleGroup"],
			"module" => [\BigTree\Services\AutoModuleService::class, "aiResolveModuleForm"],
			"page parent" => [\BigTree\Services\PageService::class, "aiPriorPageChange"],
			"redirect destination" => [\BigTree\Services\FourOhFourService::class, "aiCheckRedirectDestination"],
		];
	}

	function test_container_seams_hint_at_a_pending_proposal() {
		$missing = [];

		foreach (ai_sequencing_hinting_seams() as $label => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);

			T::ok($body !== "", "{$method}'s source was read");

			if (strpos($body, '"prior_change"') === false) {
				$missing[] = "{$label} ({$method})";
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"every seam that resolves a container by name can say it is staged rather than absent"
		);
	}

	/**
	 * And the model has to be able to see its own unapproved work, or it cannot
	 * sequence anything: proposalOutcomeReplay skipped PENDING entirely.
	 */
	function test_pending_proposals_are_replayed_to_the_model() {
		$replay = new ReflectionMethod(AIChatService::class, "proposalOutcomeReplay");
		$replay->setAccessible(true);

		$note = (string)$replay->invoke(null, [[
			"proposal_id" => "prop-group-1",
			"tool" => "create_callout_group",
			"summary" => "Create a new callout group “Promos”.",
			"status" => ProposalStore::PENDING,
			"result" => null,
		]]);

		T::ok($note !== "", "a pending proposal produces a replay note");
		T::ok(strpos($note, "create_callout_group") !== false, "naming the tool that staged it");
		T::ok(strpos($note, "Promos") !== false, "and what it would create");
		T::ok(
			strpos($note, "nothing has been written") !== false,
			"framed as unwritten, so the model doesn't treat it as done"
		);
	}

	/**
	 * A lapsed proposal is not something the model should wait for.
	 *
	 * A row only flips to `expired` when someone tries to load it (loadOwned), so a
	 * conversation resumed after the 24h TTL still reads PENDING in the presented
	 * rows the replay is built from. Listing one told the model a card was
	 * outstanding — and not to re-propose it — when it can never be approved, which
	 * is the opposite of the sequencing the pending block exists to enable.
	 * pendingForConversation() excludes them for the same reason.
	 */
	function test_a_lapsed_pending_proposal_is_not_replayed_as_outstanding() {
		$replay = new ReflectionMethod(AIChatService::class, "proposalOutcomeReplay");
		$replay->setAccessible(true);

		$row = [
			"proposal_id" => "prop-group-1",
			"tool" => "create_callout_group",
			"summary" => "Create a new callout group “Promos”.",
			"status" => ProposalStore::PENDING,
			"result" => null,
		];

		$live = (string)$replay->invoke(null, [$row + ["expires_at" => date("Y-m-d H:i:s", time() + 3600)]]);
		$lapsed = (string)$replay->invoke(null, [$row + ["expires_at" => date("Y-m-d H:i:s", time() - 60)]]);

		T::ok(strpos($live, "Promos") !== false, "a live pending proposal is still replayed");
		T::equals($lapsed, "", "one past its TTL is not — it can never be approved");
	}

	/** The prompt has to agree with the backend, or the model improvises around it. */
	function test_the_prompt_teaches_sequencing() {
		$prompt = (new AIChatService())->systemPrompt(ai_fake_user(2));

		T::ok(
			strpos($prompt, "only real once the user approves") !== false,
			"the prompt says a change is only real once approved"
		);
		T::ok(
			strpos($prompt, "needs_prior_change") !== false,
			"and names the result the backend returns"
		);
	}
