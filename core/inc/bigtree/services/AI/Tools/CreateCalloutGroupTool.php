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
	 * Developer-only, like every callout write.
	 *
	 * The group can be filled at creation. It used to start empty always, so
	 * "group these four callouts under Promos" was one proposal plus four
	 * update_callout proposals — and each of those had to wait for the group's own
	 * approval before it could even be staged, since the group has no id until then.
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
					. "a page region restricts which callouts editors may place in it. Name the callouts that should "
					. "be in it, or leave it empty and pass the group's name as create_callout's `group` later. If "
					. "the user hasn't said which callouts belong in the group, ask them — list_callouts shows what "
					. "exists.",
				[
					"type" => "object",
					"properties" => [
						"name" => [
							"type" => "string",
							"description" => "Name of the group, e.g. \"Sidebar\" (required).",
						],
						"callouts" => [
							"type" => "array",
							"items" => ["type" => "string"],
							"description" => "Callouts to put in the group, by id or name (use list_callouts to see "
								. "them). A callout belongs to one group at a time, so any listed here leaves the "
								. "group it is in now — the proposal card says which.",
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
