<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	class SearchTagsTool extends AbstractSearchTool {
		public function name(): string {

			return "search_tags";
		}

		/**
		 * Available to everyone, matching GET /tags/search (level 0) and the tag
		 * browser any editor gets in the page editor.
		 *
		 * This was administrator-only while `can_attach_tags` is true for every user
		 * — so an editor was told they may tag things and given no way to discover
		 * what tags exist. Reading the vocabulary is not the privileged part;
		 * *coining* a tag is, and that gate lives on add_tags where it belongs.
		 */
		public function isAvailable($user): bool {

			return true;
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Search the site's existing content tags by name.",
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
				$rows = array_merge($rows, $this->backend->searchTags($term, $context->limit));
			}

			return AIToolResult::ok(
				["tags" => $this->uniqueById($rows)],
				["tags" => $rows]
			);
		}
	}
