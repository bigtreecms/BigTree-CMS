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
					. "keyword. Encrypted setting values are never shown. The result says whether more settings "
					. "exist beyond the window you asked for (has_more) and how many it actually contains "
					. "(returned) — a site with very long settings can end the window early, so page by "
					. "advancing offset by `returned`, never by `limit`.",
				[
					"type" => "object",
					"properties" => [
						"query" => [
							"type" => "string",
							"description" => "Optional keyword to filter settings by id, name, or description.",
						],
						"limit" => [
							"type" => "integer",
							"description" => "How many settings to return (max 100).",
						],
						"offset" => [
							"type" => "integer",
							"description" => "How many settings to skip, for paging past the first window.",
						],
					],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ($context->userLevel() < 1) {

				return AIToolResult::denied("Only administrators can read settings.");
			}

			// The turn's shared row limit is the default rather than the ceiling: a site
			// with more than that many settings used to have no call the model could
			// make to see the rest, which made "what settings does this site have?"
			// unanswerable past the eighth.
			$limit = (int)($args["limit"] ?? 0);
			$result = $this->backend->aiGetSettings(
				(string)($args["query"] ?? ""),
				$limit > 0 ? $limit : $context->limit,
				(int)($args["offset"] ?? 0),
				$context->user
			);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			return AIToolResult::ok($result);
		}
	}
