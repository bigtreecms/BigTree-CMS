<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Search the media library by file or display name. Offered to everyone; results
	 * are filtered to folders the user can edit (rank ≥ e). Read-only.
	 */
	class SearchFilesTool extends AbstractReadTool {
		/** @var ResourceToolBackend */
		private $backend;

		public function __construct(ResourceToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "search_files";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Search the media library for files by name. Returns files in folders you can access.",
				[
					"type" => "object",
					"properties" => [
						"query" => [
							"type" => "string",
							"description" => "Filename or display-name keywords to search for.",
						],
					],
					"required" => ["query"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$query = trim((string)($args["query"] ?? ""));

			if ($query === "") {

				return AIToolResult::error("A search query is required.");
			}

			return AIToolResult::ok($this->backend->aiSearchFiles($query, $context->limit, $context->user));
		}
	}
