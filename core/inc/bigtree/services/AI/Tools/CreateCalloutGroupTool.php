<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase creation of a callout group.
	 *
	 * Exists to give create_callout's "which group?" question an answer other than
	 * the groups that already exist. A page region restricted to a group only offers
	 * callouts in that group, so without a way to make one, a new callout for a new
	 * kind of region could be created and still be unusable where it was wanted.
	 * Developer-only, like every callout write; the group starts empty.
	 */
	class CreateCalloutGroupTool extends AbstractDeveloperTool {
		/** @var CalloutToolBackend */
		private $backend;

		public function __construct(CalloutToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_callout_group";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose creating a new callout group (developer only). Requires approval. Callout groups are how "
					. "a page region restricts which callouts editors may place in it. The group is created empty; "
					. "pass its name as create_callout's `group` to put a new callout in it.",
				[
					"type" => "object",
					"properties" => [
						"name" => [
							"type" => "string",
							"description" => "Name of the group, e.g. \"Sidebar\" (required).",
						],
					],
					"required" => ["name"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["name"] ?? "")) === "") {

				return AIToolResult::error("A callout group name is required.");
			}

			$validation = $this->backend->aiValidateCalloutGroupCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
