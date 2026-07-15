<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Browse a media-library folder (its subfolders and files). Offered to everyone;
	 * the backend denies a folder the user cannot edit, so listing is gated on folder
	 * rank ≥ e. Read-only — the assistant never uploads or moves files.
	 */
	class ListResourcesTool extends AbstractReadTool {
		/** @var ResourceToolBackend */
		private $backend;

		public function __construct(ResourceToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "list_resources";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List the subfolders and files in a media-library folder (use folder 0 for the top level).",
				[
					"type" => "object",
					"properties" => [
						"folder" => [
							"type" => "integer",
							"description" => "Id of the folder to list (0 = top level, the default).",
						],
					],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$result = $this->backend->aiListResources((int)($args["folder"] ?? 0), $context->limit, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			return AIToolResult::ok($result);
		}
	}
