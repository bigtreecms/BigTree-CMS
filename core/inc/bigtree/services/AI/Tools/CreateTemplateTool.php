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
							"description" => "Minimum admin level allowed to use the template. Must be 0 (any editor, "
								. "the default), 1 (administrators) or 2 (developers) — no other value is valid.",
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
									"id" => [
										"type" => "string",
										"description" => "Field id — letters, numbers and underscores only, starting with a "
											. "letter or underscore (e.g. \"page_header\"). Used exactly as given: it is the key "
											. "the field's content is stored under, and it becomes a PHP variable in the "
											. "template's render file.",
									],
									"type" => ["type" => "string", "description" => "Field type (e.g. text, textarea, html, image)."],
									"title" => ["type" => "string", "description" => "Field label."],
									"subtitle" => ["type" => "string", "description" => "Optional help text."],
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
										"description" => "Whether a page using this template must fill this field in. "
											. "Any other field configuration (list options, image sizes, subfields) is "
											. "set in Developer → Templates.",
									],
									"default" => [
										"type" => "string",
										"description" => "Starting value for the field. It is what a page stores for "
											. "this field until an editor changes it, so a template's front end never "
											. "renders an empty region. Omit for no default.",
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
