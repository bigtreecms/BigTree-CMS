<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\CapabilitySummary;

	/**
	 * Read recent audit-trail entries — who changed what, when, and through which
	 * surface.
	 *
	 * "What has the assistant changed this week?" was only answerable in the Debug UI,
	 * which is exactly the question an AI-assisted workflow needs answered chat-side.
	 * Pass via="ai_assistant" for changes approved through a proposal card.
	 *
	 * Developer-only (matching `GET /audit`): the trail spans every table on the
	 * site, so a lower-level
	 * read would leak the existence and edit history of content the caller can't see.
	 */
	class GetAuditTrailTool extends AbstractReadTool {
		/** @var AuditToolBackend */
		private $backend;

		public function __construct(AuditToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_audit_trail";
		}

		public function isAvailable($user): bool {

			// Matches `GET /audit`'s declared level (routes/audit.php), not the
			// administrator gate this used to carry — see AuditService::aiAuditTrail.
			return CapabilitySummary::level($user) >= 2;
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Look up recent changes recorded in the audit trail: what was changed, by whom, when, and "
					. "whether it came through the assistant. Use via=\"ai_assistant\" to list only changes the "
					. "user approved from a proposal card. Developer only.",
				[
					"type" => "object",
					"properties" => [
						"via" => [
							"type" => "string",
							"description" => "Filter by change source. Use \"ai_assistant\" for changes approved "
								. "through the assistant. Omit for changes from any source.",
						],
						"table" => [
							"type" => "string",
							"description" => "Filter to one database table, e.g. \"bigtree_pages\".",
						],
						"entry" => [
							"type" => "string",
							"description" => "Filter to one record's id within that table, e.g. \"42\" for page 42. "
								. "Use with `table` to answer \"what has happened to this page?\".",
						],
						"user_id" => [
							"type" => "integer",
							"description" => "Filter to changes made by one user.",
						],
						"since" => [
							"type" => "string",
							"description" => "Only changes on or after this date, e.g. \"2026-07-01\" or \"-7 days\".",
						],
						"limit" => [
							"type" => "integer",
							"description" => "Maximum entries to return (default 25, max 100).",
						],
					],
					"required" => [],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$limit = (int)($args["limit"] ?? 25);
			$result = $this->backend->aiAuditTrail($args, $limit > 0 ? $limit : 25, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			return AIToolResult::ok($result);
		}
	}
