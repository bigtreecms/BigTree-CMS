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
									"id" => [
										"type" => "string",
										"description" => "Field id — letters, numbers, underscores and hyphens only (e.g. "
											. "\"headline\"). Used exactly as given: it is the key the field's content is "
											. "stored under.",
									],
									"type" => ["type" => "string"],
									"title" => ["type" => "string"],
									"subtitle" => ["type" => "string"],
									"options" => [
										"type" => "array",
										"description" => "Choices for a \"list\" field, in order. Strings, or "
											. "{value, description} objects when the stored value differs from the "
											. "label. A list field with no options renders as an empty select — and "
											. "if it is also required, nobody can save the record at all — so this "
											. "is required when type is \"list\".",
										"items" => ["type" => "string"],
									],
									"required" => [
										"type" => "boolean",
										"description" => "Whether an editor placing this callout must fill this field "
											. "in. Any other field configuration is set in Developer → Callouts.",
									],
									"default" => [
										"type" => "string",
										"description" => "Starting value for the field, used until an editor changes "
											. "it. Omit for no default.",
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
