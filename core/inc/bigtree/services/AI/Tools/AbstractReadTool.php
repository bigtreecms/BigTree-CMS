<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolInterface;

	/**
	 * Shared base for the Phase 4 read tools that wrap a domain service other than
	 * search (pages, templates, resources, settings, pending changes). Unlike
	 * AbstractSearchTool it holds no backend of its own — each tool injects the seam
	 * it needs — so read tools across different domains can reuse kind()/isAvailable()
	 * defaults and the OpenAI function-definition helper without a shared dependency.
	 *
	 * isAvailable() defaults to open; tools that gate on level (get_settings, …)
	 * override it and re-check inside execute().
	 */
	abstract class AbstractReadTool implements AIToolInterface {
		public function kind(): string {

			return "read";
		}

		public function isAvailable($user): bool {

			return true;
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
