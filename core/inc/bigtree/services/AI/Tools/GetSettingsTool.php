<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\CapabilitySummary;

	/**
	 * Read the site's settings (values included, encrypted ones omitted). An
	 * administrator concern — hidden from editors so the model never offers it — and
	 * re-checked in the backend.
	 */
	class GetSettingsTool extends AbstractReadTool {
		/** @var SettingToolBackend */
		private $backend;

		public function __construct(SettingToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_settings";
		}

		public function isAvailable($user): bool {

			return CapabilitySummary::level($user) >= 1;
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List the site's settings and their current values (administrators). Optionally filter by a "
					. "keyword. Encrypted setting values are never shown.",
				[
					"type" => "object",
					"properties" => [
						"query" => [
							"type" => "string",
							"description" => "Optional keyword to filter settings by id, name, or description.",
						],
					],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ($context->userLevel() < 1) {

				return AIToolResult::denied("Only administrators can read settings.");
			}

			$result = $this->backend->aiGetSettings((string)($args["query"] ?? ""), $context->limit, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			return AIToolResult::ok($result);
		}
	}
