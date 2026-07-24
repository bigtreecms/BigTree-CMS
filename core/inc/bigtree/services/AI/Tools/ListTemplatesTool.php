<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\FieldTypeService;

	/**
	 * List the site's page templates. Offered to every user so the assistant can name
	 * real templates when helping create or edit a page (a non-developer can't author
	 * a template, but does need to pick one).
	 */
	class ListTemplatesTool extends AbstractReadTool {
		/** @var TemplateToolBackend */
		private $backend;

		public function __construct(TemplateToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "list_templates";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List the available page templates (id, name, and how many fields each has). Use this to offer "
					. "a real template when creating or editing a page.",
				[
					"type" => "object",
					"properties" => new \stdClass(),
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$result = ["templates" => $this->backend->aiListTemplates()];

			// A developer authoring a template from scratch (create_template) has no
			// existing one to read via get_template, so surface the valid fields[].type
			// ids here too — the first proposal names a real type instead of guessing
			// (B1). Editors only pick a template, so it would be noise for them.
			if ($context->userLevel() >= 2) {
				$result["field_types"] = FieldTypeService::aiFieldTypeCatalog("templates");
			}

			return AIToolResult::ok($result);
		}
	}
