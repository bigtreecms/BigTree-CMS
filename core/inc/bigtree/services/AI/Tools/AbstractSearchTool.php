<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolInterface;

	/**
	 * Shared base for the read-only search tools. Holds the backend seam, defaults
	 * kind() to "read", and provides the small helpers each tool reuses (keyword
	 * normalization of the model's query arg and id-dedupe).
	 */
	abstract class AbstractSearchTool implements AIToolInterface {
		/** @var SearchToolBackend */
		protected $backend;

		public function __construct(SearchToolBackend $backend) {
			$this->backend = $backend;
		}

		public function kind(): string {

			return "read";
		}

		public function isAvailable($user): bool {

			return true;
		}

		/**
		 * A single-string "query" parameter schema, shared by most search tools.
		 *
		 * @return array<string,mixed>
		 */
		protected function queryParameters(): array {

			return [
				"type" => "object",
				"properties" => [
					"query" => [
						"type" => "string",
						"description" => "Search keywords or natural language phrase.",
					],
				],
				"required" => ["query"],
			];
		}

		/**
		 * Reduce the model's query arg to search terms. Falls back to the raw
		 * phrase when no keywords extract (so short single words still search).
		 *
		 * @param array<string,mixed> $args
		 * @return list<string>
		 */
		protected function queryTerms(array $args): array {
			$query = trim((string)($args["query"] ?? ""));
			$terms = $this->backend->extractKeywords($query);

			if ($terms) {
				return $terms;
			}

			return $query !== "" ? [$query] : [];
		}

		/**
		 * @param list<array<string,mixed>> $rows
		 * @return list<array<string,mixed>>
		 */
		protected function uniqueById(array $rows): array {
			$seen = [];
			$out = [];

			foreach ($rows as $row) {
				$id = (string)($row["id"] ?? "");

				if ($id === "" || isset($seen[$id])) {
					continue;
				}

				$seen[$id] = true;
				$out[] = $row;
			}

			return $out;
		}

		/**
		 * Wraps an OpenAI-style function tool definition.
		 *
		 * @param array<string,mixed> $parameters
		 * @return array<string,mixed>
		 */
		protected function functionDefinition(string $name, string $description, array $parameters): array {

			return [
				"type" => "function",
				"function" => [
					"name" => $name,
					"description" => $description,
					"parameters" => $parameters,
				],
			];
		}
	}
