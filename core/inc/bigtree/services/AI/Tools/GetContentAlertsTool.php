<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * List pages flagged as stale — the dashboard's content alerts.
	 *
	 * "What content needs updating?" is an ordinary editor ask that had no tool and
	 * no decline line, so the model rediscovered the wall by failing. Pairs with
	 * update_page's max_age, so the assistant can both see stale content and fix the
	 * tracking on it. Read-only; the backend filters every row by view access.
	 */
	class GetContentAlertsTool extends AbstractReadTool {
		/** @var DashboardToolBackend */
		private $backend;

		public function __construct(DashboardToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_content_alerts";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List pages that are overdue for review — the dashboard's content alerts. Returns pages past "
					. "their own max_age (the site-wide staleness rule) and pages past your personal alert "
					. "threshold, with how many days old each is. Use this for \"what content is stale?\" or "
					. "\"what needs updating?\".",
				[
					"type" => "object",
					"properties" => [
						"limit" => [
							"type" => "integer",
							"description" => "Maximum pages to return per group (default 25, max 100).",
						],
					],
					"required" => [],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$limit = (int)($args["limit"] ?? 25);
			$result = $this->backend->aiContentAlerts($context->user, $limit > 0 ? $limit : 25);

			return AIToolResult::ok($result);
		}
	}
