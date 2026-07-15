<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * List the pending changes relevant to the user — ones they submitted and ones
	 * awaiting their approval. Offered to everyone; the backend scopes the list to
	 * what the user actually owns or may publish, so no change leaks.
	 */
	class GetPendingChangesTool extends AbstractReadTool {
		/** @var PendingChangeToolBackend */
		private $backend;

		public function __construct(PendingChangeToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_pending_changes";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List pending (unpublished) changes relevant to the user: ones they submitted and ones "
					. "awaiting their approval. Use this to answer \"what's waiting to be published?\".",
				[
					"type" => "object",
					"properties" => new \stdClass(),
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {

			return AIToolResult::ok($this->backend->aiPendingChanges($context->limit, $context->user));
		}
	}
