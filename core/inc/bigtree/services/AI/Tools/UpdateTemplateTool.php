<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Developer-only two-phase edit of a page template's name, minimum level, or
	 * fields. Passing "fields" replaces the template's field list wholesale, so the
	 * preview shows the before/after field counts; a field carried over by id keeps
	 * the settings the assistant's field shape can't express. Writes nothing until
	 * approved.
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
					. "properties you want to change. Supplying \"fields\" REPLACES the whole field list, so include "
					. "every field the template should end up with — use get_template first if you don't know them. "
					. "Fields you carry over keep their existing configuration.",
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
							"description" => "The template's complete new field list — any field you omit is removed, "
								. "orphaning its content on every page using the template. A field you carry over by "
								. "id keeps the configuration set in Developer → Templates (validation rules, list "
								. "options, image sizes), so restate only what you are changing.",
							"items" => [
								"type" => "object",
								"properties" => [
									"id" => ["type" => "string"],
									"type" => [
										"type" => "string",
										"description" => "Omit to keep an existing field's type. Changing it discards "
											. "the configuration set for the old type.",
									],
									"title" => ["type" => "string", "description" => "Omit to keep an existing field's label."],
									"subtitle" => ["type" => "string"],
									"required" => [
										"type" => "boolean",
										"description" => "Applies to newly added fields only; an existing field keeps "
											. "the validation rules set in Developer → Templates.",
									],
								],
								"required" => ["id"],
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
