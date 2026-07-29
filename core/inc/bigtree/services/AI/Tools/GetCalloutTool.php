<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Fetch one callout's definition, including its full field list.
	 *
	 * Pairs with update_callout, whose `fields` argument replaces the whole list: the
	 * assistant has to be able to read what a callout already has before proposing a
	 * change, or "add a field" turns into guessing the rest.
	 *
	 * Level 0, matching `GET /callouts/{id}`. It used to be developer-gated, which
	 * made it stricter than the REST route it mirrors — the only read tool in the
	 * catalogue that was, and not a decision anybody made (audit #13 D4). Editing a
	 * callout is still developer-only.
	 */
	class GetCalloutTool extends AbstractReadTool {
		/** @var CalloutToolBackend */
		private $backend;

		public function __construct(CalloutToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_callout";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Fetch a single callout's definition by id: its name, level, display field, and complete list of "
					. "editable fields. Use this before update_callout, which replaces the whole field list.",
				[
					"type" => "object",
					"properties" => [
						"callout_id" => [
							"type" => "string",
							"description" => "Callout id.",
						],
					],
					"required" => ["callout_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$callout_id = trim((string)($args["callout_id"] ?? ""));

			if ($callout_id === "") {

				return AIToolResult::error("A callout_id is required.");
			}

			$result = $this->backend->aiGetCallout($callout_id, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			return AIToolResult::ok($result);
		}
	}
