<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\EmbeddingService;

	class SemanticSearchTool extends AbstractSearchTool {
		public function name(): string {

			return "semantic_search";
		}

		public function isAvailable($user): bool {

			return EmbeddingService::isEnabled();
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Vector/semantic search across pages and module content (aboutness / paraphrase). Prefer for natural-language meaning queries.",
				$this->queryParameters()
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (!EmbeddingService::isEnabled()) {
				return AIToolResult::denied("Semantic search is not enabled.");
			}

			// Semantic search runs on the full phrase, not extracted keywords.
			$query = trim((string)($args["query"] ?? ""));

			if ($query === "") {
				return AIToolResult::error("query required");
			}

			$hits = $this->backend->semanticSearch($query, $context->limit, $context->user);

			return AIToolResult::ok($hits, [
				"pages" => $hits["pages"] ?? [],
				"entries" => $hits["entries"] ?? [],
			]);
		}
	}
