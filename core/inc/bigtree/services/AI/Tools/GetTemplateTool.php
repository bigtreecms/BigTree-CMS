<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Fetch one template's full definition, including its resource fields. Offered to
	 * every user (reading a template's shape is not a developer-only concern).
	 */
	class GetTemplateTool extends AbstractReadTool {
		/** @var TemplateToolBackend */
		private $backend;

		public function __construct(TemplateToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_template";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Fetch a single page template's definition and its list of fields by id.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Template id.",
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$id = trim((string)($args["id"] ?? ""));

			if ($id === "") {

				return AIToolResult::error("A template id is required.");
			}

			$result = $this->backend->aiGetTemplate($id);

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			return AIToolResult::ok($result);
		}
	}
