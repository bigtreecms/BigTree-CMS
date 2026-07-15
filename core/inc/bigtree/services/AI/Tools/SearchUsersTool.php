<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\CapabilitySummary;

	class SearchUsersTool extends AbstractSearchTool {
		public function name(): string {

			return "search_users";
		}

		/**
		 * User records are an administrator concern — hidden from editors.
		 */
		public function isAvailable($user): bool {

			return CapabilitySummary::level($user) >= 1;
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Search admin users by name, email, or company (administrators).",
				$this->queryParameters()
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ($context->userLevel() < 1) {
				return AIToolResult::denied("Only administrators can search users.");
			}

			$terms = $this->queryTerms($args);

			if (!$terms) {
				return AIToolResult::error("query required");
			}

			$rows = [];

			foreach ($terms as $term) {
				$rows = array_merge($rows, $this->backend->searchUsers($term, $context->limit));
			}

			return AIToolResult::ok(
				["users" => $this->uniqueById($rows)],
				["users" => $rows]
			);
		}
	}
