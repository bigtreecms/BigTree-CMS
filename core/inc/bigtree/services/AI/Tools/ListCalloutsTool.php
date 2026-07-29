<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * List every callout and callout group.
	 *
	 * list_templates exists precisely so the model can name a real template before
	 * proposing one. The callout half had no equivalent: get_callout needed a guessed
	 * id, and create_callout's `group` argument had nothing to enumerate — which made
	 * its needs_input branch the normal path rather than the exception.
	 *
	 * Level 0, matching `GET /callouts` — see GetCalloutTool (audit #13 D4). Editing
	 * a callout is still developer-only.
	 */
	class ListCalloutsTool extends AbstractReadTool {
		/** @var CalloutToolBackend */
		private $backend;

		public function __construct(CalloutToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "list_callouts";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List every callout (id, name, level, field count, the groups it belongs to) and every callout "
					. "group. Use this to name a real callout before get_callout or update_callout, or a real "
					. "group before create_callout.",
				[
					"type" => "object",
					"properties" => new \stdClass(),
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$result = $this->backend->aiListCallouts($context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			return AIToolResult::ok($result);
		}
	}
