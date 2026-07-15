<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

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

			return AIToolResult::ok(["templates" => $this->backend->aiListTemplates()]);
		}
	}
