<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\CapabilitySummary;

	class SearchTagsTool extends AbstractSearchTool {
		public function name(): string {

			return "search_tags";
		}

		/**
		 * Tags are an administrator concern — hide the tool from editors entirely so
		 * the model never offers it.
		 */
		public function isAvailable($user): bool {

			return CapabilitySummary::level($user) >= 1;
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Search content tags (administrators).",
				$this->queryParameters()
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ($context->userLevel() < 1) {
				return AIToolResult::denied("Only administrators can search tags.");
			}

			$terms = $this->queryTerms($args);

			if (!$terms) {
				return AIToolResult::error("query required");
			}

			$rows = [];

			foreach ($terms as $term) {
				$rows = array_merge($rows, $this->backend->searchTags($term, $context->limit));
			}

			return AIToolResult::ok(
				["tags" => $this->uniqueById($rows)],
				["tags" => $rows]
			);
		}
	}
