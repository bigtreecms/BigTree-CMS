<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	class SearchModuleEntriesTool extends AbstractSearchTool {
		public function name(): string {

			return "search_module_entries";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Search content rows inside modules the user can access.",
				$this->queryParameters()
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$terms = $this->queryTerms($args);

			if (!$terms) {
				return AIToolResult::error("query required");
			}

			$groups = [];

			foreach ($terms as $term) {
				$groups = array_merge($groups, $this->backend->searchModuleEntries($term, $context->limit, $context->user));
			}

			return AIToolResult::ok(
				["entries" => $groups],
				["entries" => $groups]
			);
		}
	}
