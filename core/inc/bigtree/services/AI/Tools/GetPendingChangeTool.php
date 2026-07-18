<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Fetch one pending change with the field-level diff it would apply.
	 *
	 * get_pending_changes lists changes but shows only title/table/type, which meant
	 * a user could be walked into approving a publish without ever seeing what it
	 * changes. Scoped in the backend to the caller's own changes and ones they can
	 * publish.
	 */
	class GetPendingChangeTool extends AbstractReadTool {
		/** @var PendingChangeToolBackend */
		private $backend;

		public function __construct(PendingChangeToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_pending_change";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Fetch a single pending change by id, including the field-by-field diff it would apply when "
					. "published. Use this before proposing publish_pending_change so the user can see what they "
					. "are approving.",
				[
					"type" => "object",
					"properties" => [
						"change_id" => [
							"type" => "integer",
							"description" => "The pending change's id.",
						],
					],
					"required" => ["change_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$id = (int)($args["change_id"] ?? 0);

			if ($id < 1) {

				return AIToolResult::error("A change_id is required.");
			}

			$result = $this->backend->aiGetPendingChange($id, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			return AIToolResult::ok($result);
		}
	}
