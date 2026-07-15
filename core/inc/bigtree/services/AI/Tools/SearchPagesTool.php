<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	class SearchPagesTool extends AbstractSearchTool {
		public function name(): string {

			return "search_pages";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Search CMS pages by title / nav title.",
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
				$rows = array_merge($rows, $this->backend->searchPages($term, $context->limit, $context->user));
			}

			return AIToolResult::ok(
				["pages" => $this->uniqueById($rows)],
				["pages" => $rows]
			);
		}
	}
