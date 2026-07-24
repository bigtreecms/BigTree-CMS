<?php
	/**
	 * Audit #9 Part A against the real store.
	 *
	 * AISequencingTest exercises the sequencing logic over a fake store, which proves
	 * the branching but not the query underneath it. These legs run the real
	 * ProposalStore against the database: the pending lookup itself (a new SQL
	 * statement no other test executes), and one end-to-end staging where a genuinely
	 * persisted proposal is what turns a needs_input into a needs_prior_change.
	 */

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;
	use BigTree\Services\AI\Tools\AbstractMutatingTool;

	/** The conversation these fixtures live in; torn down in every finally. */
	const PARITY_A9_CONVERSATION = 987654009;

	function parity_a9_probe_tool(ProposalStore $store): AbstractMutatingTool {

		// The same stand-in the audit #5 lock probe uses: stageFromValidation is the
		// shared path, so what it does here is what every tool in the catalog does.
		return new class ($store) extends AbstractMutatingTool {
			public function name(): string {

				return "zz_audit9_sequencing_probe";
			}

			public function definition($user): array {

				return $this->functionDefinition($this->name(), "test probe", []);
			}

			public function execute(array $args, AIToolContext $context): AIToolResult {

				return $this->stageFromValidation($args, $context, $this->name());
			}
		};
	}

	/**
	 * The pending lookup: scoped to its conversation, narrowed by tool, and blind to
	 * proposals that have already been resolved.
	 */
	function test_parity_ai_pending_lookup_is_scoped_and_filtered() {
		if (!parity_a5_proposals_ready()) {

			return;
		}

		$store = new ProposalStore();
		$user = ["id" => 987654009];

		try {
			$group = $store->create(
				$user,
				PARITY_A9_CONVERSATION,
				"create_callout_group",
				"Create a new callout group “Promos”.",
				[],
				["name" => "Promos"]
			);
			$store->create($user, PARITY_A9_CONVERSATION, "create_page", "Create page “Pricing”.", [], ["route" => "pricing"]);
			$store->create($user, PARITY_A9_CONVERSATION + 1, "create_callout_group", "Another conversation.", [], ["name" => "Elsewhere"]);

			$all = $store->pendingForConversation(PARITY_A9_CONVERSATION);
			T::equals(count($all), 2, "the lookup returns this conversation's pending proposals");

			$groups = $store->pendingForConversation(PARITY_A9_CONVERSATION, ["create_callout_group"]);
			T::equals(count($groups), 1, "and narrows to the named tool");
			T::equals((string)$groups[0]["id"], (string)$group["id"], "returning the right row");
			T::ok(isset($groups[0]["payload"]), "with its payload, so the caller can match on what it would create");

			// A resolved proposal is not a prerequisite — its change either happened or
			// was declined, and either way it is no longer "staged and waiting".
			$store->markResolved((string)$group["id"], ProposalStore::APPROVED, ["mode" => "created"]);
			T::equals(
				count($store->pendingForConversation(PARITY_A9_CONVERSATION, ["create_callout_group"])),
				0,
				"an approved proposal drops out of the pending set"
			);

			T::equals(
				count($store->pendingForConversation(0)),
				0,
				"and a stateless turn (no conversation) matches nothing"
			);
		} finally {
			SQL::query(
				"DELETE FROM " . ProposalStore::TABLE . " WHERE conversation IN (?, ?)",
				PARITY_A9_CONVERSATION,
				PARITY_A9_CONVERSATION + 1
			);
		}
	}

	/**
	 * End to end: a persisted proposal is what turns "that group doesn't exist" into
	 * "approve the card that creates it", and a non-blocking dependency is recorded
	 * on the row that does stage.
	 */
	function test_parity_ai_staging_reads_real_pending_proposals() {
		if (!parity_a5_proposals_ready()) {

			return;
		}

		$store = new ProposalStore();
		$actor = (object)["id" => 987654009, "level" => 2, "permissions" => []];
		$context = new AIToolContext($actor, 8, (string)PARITY_A9_CONVERSATION);
		$tool = parity_a9_probe_tool($store);

		$blocked = [
			"needs_input" => ["question" => "Which group?", "options" => []],
			"prior_change" => [
				"tool" => "create_callout_group",
				"value" => "Promos",
				"keys" => ["name"],
				"label" => "A callout group called “Promos”",
			],
		];

		try {
			T::equals(
				$tool->execute($blocked, $context)->type,
				AIToolResult::NEEDS_INPUT,
				"with nothing staged, the seam's own question stands"
			);

			$group = $store->create(
				$actor,
				PARITY_A9_CONVERSATION,
				"create_callout_group",
				"Create a new callout group “Promos”.",
				[],
				["name" => "Promos"]
			);

			$result = $tool->execute($blocked, $context);
			T::equals($result->type, AIToolResult::NEEDS_PRIOR_CHANGE, "the staged group changes the answer");
			T::equals($result->proposal_id, (string)$group["id"], "naming the real proposal to approve first");

			// And the non-blocking half, staged against the same live row.
			$staged = $tool->execute([
				"ok" => true,
				"summary" => "Redirect /old to /promos.",
				"preview" => [],
				"payload" => ["from" => "/old"],
				"prior_change" => [
					"tool" => "create_callout_group",
					"value" => "Promos",
					"keys" => ["name"],
					"label" => "A callout group called “Promos”",
					"blocking" => false,
				],
			], $context);

			T::equals($staged->type, AIToolResult::PROPOSAL, "a non-blocking prerequisite still stages");

			$row = $store->loadOwned((string)$staged->proposal_id, $actor);
			$payload = $store->decodePayload($row);

			T::equals(
				(string)($payload[AbstractMutatingTool::DEPENDS_ON_KEY]["id"] ?? ""),
				(string)$group["id"],
				"and the persisted payload records the prerequisite"
			);
			T::ok(
				strpos((string)$row["summary"], "Approve that one first") !== false,
				"the stored card says which to approve first"
			);
		} finally {
			SQL::query("DELETE FROM " . ProposalStore::TABLE . " WHERE conversation = ?", PARITY_A9_CONVERSATION);
		}
	}
