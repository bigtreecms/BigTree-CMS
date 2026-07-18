<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase rejection of a pending change — the decline half of the review flow
	 * that publish_pending_change previously had no counterpart for, which meant a
	 * reviewer working in chat could only ever say yes.
	 *
	 * Allowed for a publisher or the change's own author (withdrawing your own
	 * unpublished draft needs nobody's approval). Rejecting is irreversible, and for
	 * a NEW draft it discards content that exists nowhere else, so the proposal
	 * states which of those two cases applies.
	 */
	class RejectPendingChangeTool extends AbstractMutatingTool {
		/** @var PendingChangeToolBackend */
		private $backend;

		public function __construct(PendingChangeToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "reject_pending_change";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose rejecting (discarding) a pending change. Requires the user's approval. Allowed for a "
					. "publisher, or for the person who submitted the change. This cannot be undone — for a new "
					. "draft it discards content that was never published. Use get_pending_change first so the "
					. "user can see what is being discarded.",
				[
					"type" => "object",
					"properties" => [
						"change_id" => [
							"type" => "integer",
							"description" => "The pending change's id (required).",
						],
					],
					"required" => ["change_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ((int)($args["change_id"] ?? 0) < 1) {

				return AIToolResult::error("A change_id is required.");
			}

			$validation = $this->backend->aiValidateRejectChange($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
