<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Developer-only two-phase edit of a page template's name, minimum level, or
	 * fields. Passing "fields" replaces the template's field list wholesale, so the
	 * preview shows the before/after field counts. Writes nothing until approved.
	 */
	class UpdateTemplateTool extends AbstractDeveloperTool {
		/** @var TemplateToolBackend */
		private $backend;

		public function __construct(TemplateToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "update_template";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose editing an existing page template (developer only). Requires approval. Only include the "
					. "properties you want to change; passing \"fields\" replaces the whole field list.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Id of the template to edit (required).",
						],
						"name" => [
							"type" => "string",
							"description" => "New template name.",
						],
						"level" => [
							"type" => "integer",
							"description" => "New minimum admin level (0 editor, 1 admin, 2 developer).",
						],
						"fields" => [
							"type" => "array",
							"description" => "Replacement field list (replaces all existing fields).",
							"items" => [
								"type" => "object",
								"properties" => [
									"id" => ["type" => "string"],
									"type" => ["type" => "string"],
									"title" => ["type" => "string"],
									"subtitle" => ["type" => "string"],
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

				return AIToolResult::error("A template id is required.");
			}

			$validation = $this->backend->aiValidateTemplateUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
