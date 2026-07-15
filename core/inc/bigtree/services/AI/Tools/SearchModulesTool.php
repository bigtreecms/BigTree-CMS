<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	class SearchModulesTool extends AbstractSearchTool {
		public function name(): string {

			return "search_modules";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Search module definitions the user can access.",
				$this->queryParameters()
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$terms = $this->queryTerms($args);

			if (!$terms) {
				return AIToolResult::error("query required");
			}

			$rows = [];

			foreach ($terms as $term) {
				$rows = array_merge($rows, $this->backend->searchModules($term, $context->limit, $context->user));
			}

			return AIToolResult::ok(
				["modules" => $this->uniqueById($rows)],
				["modules" => $rows]
			);
		}
	}
