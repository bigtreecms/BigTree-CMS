<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * List a page's saved and automatic revisions.
	 *
	 * Pairs with restore_page_revision: "undo what was just done to this page" is a
	 * natural ask that had no AI path, even though restoring is an ordinary page
	 * update underneath. Needs view access only, matching the admin's own revisions
	 * panel; the backend re-checks.
	 */
	class GetPageRevisionsTool extends AbstractReadTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_page_revisions";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List the saved and automatic revisions of a page, newest first, with when each was taken and "
					. "by whom. Use this to find the revision to restore when the user wants to undo a change.",
				[
					"type" => "object",
					"properties" => [
						"page_id" => [
							"type" => "integer",
							"description" => "Id of the page whose revisions to list.",
						],
						"limit" => [
							"type" => "integer",
							"description" => "Maximum revisions to return (default 20, max 50).",
						],
					],
					"required" => ["page_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$page_id = (int)($args["page_id"] ?? 0);

			if ($page_id < 1) {

				return AIToolResult::error("A page_id is required.");
			}

			$limit = (int)($args["limit"] ?? 20);
			$result = $this->backend->aiPageRevisions($page_id, $limit > 0 ? $limit : 20, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			return AIToolResult::ok($result);
		}
	}
