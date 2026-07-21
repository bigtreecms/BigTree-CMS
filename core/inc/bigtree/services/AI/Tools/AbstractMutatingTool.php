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
		 *   ["needs_input" => ["question" => string, "options" => list<array>]] |
		 *   ["ok" => true, "summary" => string, "preview" => array, "payload" => array]
		 */
		protected function stageFromValidation(array $validation, AIToolContext $context, string $tool): AIToolResult {
			if (isset($validation["denied"])) {

				return AIToolResult::denied((string)$validation["denied"], is_array($validation["alternatives"] ?? null) ? $validation["alternatives"] : []);
			}

			if (isset($validation["error"])) {

				return AIToolResult::error((string)$validation["error"]);
			}

			// A module with more than one entry form has no safe default — each form
			// writes to its own table, so guessing would validate against one and
			// write to another. The backend hands back the choice; turn it into a
			// question for the user rather than picking for them.
			if (!empty($validation["ambiguous_form"])) {

				return AIToolResult::needsInput(
					"Which form should I use for this module?",
					array_map(function (array $form): array {

						return [
							"id" => (string)$form["id"],
							"label" => (string)($form["title"] !== "" ? $form["title"] : $form["id"]),
							"description" => "Table: " . (string)$form["table"],
						];
					}, is_array($validation["forms"] ?? null) ? $validation["forms"] : [])
				);
			}

			// The general form of the same idea: a backend that can't proceed without a
			// choice hands back the question and its options rather than guessing or
			// failing with a wall the model has to rediscover ("group does not exist").
			if (is_array($validation["needs_input"] ?? null)) {

				return AIToolResult::needsInput(
					(string)($validation["needs_input"]["question"] ?? "I need a bit more information."),
					is_array($validation["needs_input"]["options"] ?? null) ? $validation["needs_input"]["options"] : []
				);
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
