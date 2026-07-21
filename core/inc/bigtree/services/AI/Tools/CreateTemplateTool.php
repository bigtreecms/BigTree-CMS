<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Developer-only two-phase creation of a page template. Hidden from every
	 * non-developer's registry; the backend re-checks developer level at validation
	 * and approval. Stages a proposal with a preview of the template's fields — the
	 * "code-diff preview" for a developer resource — and writes nothing until approved.
	 */
	class CreateTemplateTool extends AbstractDeveloperTool {
		/** @var TemplateToolBackend */
		private $backend;

		public function __construct(TemplateToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_template";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose creating a new page template (developer only). Requires approval before anything is "
					. "created. Fields are the editable regions of a page built on the template.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Short lowercase template id (e.g. \"landing-page\"). Required.",
						],
						"name" => [
							"type" => "string",
							"description" => "Human-readable template name (defaults to the id).",
						],
						"level" => [
							"type" => "integer",
							"description" => "Minimum admin level allowed to use the template (0 editor, 1 admin, 2 developer).",
						],
						"routed" => [
							"type" => "boolean",
							"description" => "Whether the template is routed (handles its own sub-URLs).",
						],
						"fields" => [
							"type" => "array",
							"description" => "Editable fields for the template.",
							"items" => [
								"type" => "object",
								"properties" => [
									"id" => ["type" => "string", "description" => "Field id (lowercase)."],
									"type" => ["type" => "string", "description" => "Field type (e.g. text, textarea, html, image)."],
									"title" => ["type" => "string", "description" => "Field label."],
									"subtitle" => ["type" => "string", "description" => "Optional help text."],
									"required" => [
										"type" => "boolean",
										"description" => "Whether a page using this template must fill this field in. "
											. "Any other field configuration (list options, image sizes, subfields) is "
											. "set in Developer → Templates.",
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
			$validation = $this->backend->aiValidateTemplateCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
