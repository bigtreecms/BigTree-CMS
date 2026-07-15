<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolInterface;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Shared base for two-phase mutating tools. A mutating tool never changes the CMS
	 * inside execute(): it validates, stages a proposal in the ProposalStore, and
	 * returns AIToolResult::proposal(...). The actual write happens later, from the
	 * stored payload, when the user approves (see AIChatService::approveProposal).
	 *
	 * kind() is fixed to "mutate" so a driver can tell these apart from read tools.
	 */
	abstract class AbstractMutatingTool implements AIToolInterface {
		/** @var ProposalStore */
		protected $proposals;

		public function __construct(ProposalStore $proposals) {
			$this->proposals = $proposals;
		}

		public function kind(): string {

			return "mutate";
		}

		public function isAvailable($user): bool {

			return true;
		}

		/**
		 * Turn a backend validation result into a tool result: a denial or recoverable
		 * error passes straight through, and a valid one stages a proposal (scoped to
		 * the conversation) and returns its id. Every mutating tool's execute() ends
		 * here, so the validate→stage contract stays identical across the catalog and
		 * nothing is written during the turn.
		 *
		 * @param array<string,mixed> $validation One of:
		 *   ["denied" => string] | ["error" => string] |
		 *   ["ok" => true, "summary" => string, "preview" => array, "payload" => array]
		 */
		protected function stageFromValidation(array $validation, AIToolContext $context, string $tool): AIToolResult {
			if (isset($validation["denied"])) {

				return AIToolResult::denied((string)$validation["denied"], is_array($validation["alternatives"] ?? null) ? $validation["alternatives"] : []);
			}

			if (isset($validation["error"])) {

				return AIToolResult::error((string)$validation["error"]);
			}

			$conversation_id = (int)($context->conversation_id !== "" ? $context->conversation_id : 0);
			$summary = (string)($validation["summary"] ?? "");
			$preview = is_array($validation["preview"] ?? null) ? $validation["preview"] : [];
			$payload = is_array($validation["payload"] ?? null) ? $validation["payload"] : [];

			$proposal = $this->proposals->create($context->user, $conversation_id, $tool, $summary, $preview, $payload);

			return AIToolResult::proposal($summary, $preview, (string)$proposal["id"]);
		}

		/**
		 * Wraps an OpenAI-style function tool definition.
		 *
		 * @param array<string,mixed> $parameters
		 * @return array<string,mixed>
		 */
		protected function functionDefinition(string $name, string $description, array $parameters): array {

			return [
				"type" => "function",
				"function" => [
					"name" => $name,
					"description" => $description,
					"parameters" => $parameters,
				],
			];
		}
	}
