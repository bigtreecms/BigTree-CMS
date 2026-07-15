<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase publish of a pending change. Publisher access is object-scoped (it
	 * depends on the change's page or module), so the tool is offered to everyone and
	 * the backend denies a non-publisher at validation. On approval it reuses the same
	 * apply logic as the REST approve route. Writes nothing during the turn.
	 */
	class PublishPendingChangeTool extends AbstractMutatingTool {
		/** @var PendingChangeToolBackend */
		private $backend;

		public function __construct(PendingChangeToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "publish_pending_change";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose publishing a pending change so it goes live. Requires publisher access on the affected "
					. "page or module, and the user's approval.",
				[
					"type" => "object",
					"properties" => [
						"change_id" => [
							"type" => "integer",
							"description" => "Id of the pending change to publish (required).",
						],
					],
					"required" => ["change_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ((int)($args["change_id"] ?? 0) < 1) {

				return AIToolResult::error("A pending-change id is required.");
			}

			$validation = $this->backend->aiValidatePublishChange($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
