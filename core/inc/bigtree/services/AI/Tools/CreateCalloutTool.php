<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Developer-only two-phase creation of a callout. Hidden from non-developers; the
	 * backend re-checks developer level. Stages a proposal previewing the callout's
	 * fields and writes nothing until approved.
	 */
	class CreateCalloutTool extends AbstractDeveloperTool {
		/** @var CalloutToolBackend */
		private $backend;

		public function __construct(CalloutToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_callout";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose creating a new callout (developer only). Requires approval. Fields are the callout's "
					. "editable regions.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Short lowercase callout id (required).",
						],
						"name" => [
							"type" => "string",
							"description" => "Human-readable callout name (defaults to the id).",
						],
						"description" => [
							"type" => "string",
							"description" => "Optional description shown to editors.",
						],
						"level" => [
							"type" => "integer",
							"description" => "Minimum admin level allowed to use the callout (0 editor, 1 admin, 2 developer).",
						],
						"group" => [
							"type" => "string",
							"description" => "Optional callout group (id or name) to add the callout to. A page region "
								. "restricted to a group only offers callouts in that group, so an ungrouped callout "
								. "won't appear there. Use create_callout_group if the group doesn't exist yet.",
						],
						"display_field" => [
							"type" => "string",
							"description" => "Optional field id used as the callout's display label in the page editor. "
								. "Must be one of the ids in `fields`; defaults to the first text field.",
						],
						"display_default" => [
							"type" => "string",
							"description" => "Optional label shown for a callout instance whose display_field is empty "
								. "(e.g. \"Untitled callout\"). Without it such instances list as blank rows.",
						],
						"fields" => [
							"type" => "array",
							"description" => "Editable fields for the callout.",
							"items" => [
								"type" => "object",
								"properties" => [
									"id" => ["type" => "string"],
									"type" => ["type" => "string"],
									"title" => ["type" => "string"],
									"subtitle" => ["type" => "string"],
									"required" => [
										"type" => "boolean",
										"description" => "Whether an editor placing this callout must fill this field "
											. "in. Any other field configuration is set in Developer → Callouts.",
									],
								],
								"required" => ["id", "type", "title"],
							],
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["id"] ?? "")) === "") {

				return AIToolResult::error("A callout id is required.");
			}

			$validation = $this->backend->aiValidateCalloutCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
