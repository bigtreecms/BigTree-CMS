<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolInterface;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\CapabilitySummary;

	/**
	 * Grounds "what can I do?" — returns the acting user's effective capabilities as
	 * structured facts so the model can answer honestly instead of guessing. Offered
	 * to every user; the payload is derived entirely from the user's own level, so
	 * there is nothing to re-check server-side beyond that.
	 *
	 * This is the read-tool complement to CapabilitySummary::promptText (which seeds
	 * the system prompt): the model can call it mid-conversation when the user asks
	 * about their own permissions.
	 */
	class GetMyCapabilitiesTool implements AIToolInterface {
		public function name(): string {

			return "get_my_capabilities";
		}

		public function kind(): string {

			return "read";
		}

		public function isAvailable($user): bool {

			return true;
		}

		public function definition($user): array {

			return [
				"type" => "function",
				"function" => [
					"name" => $this->name(),
					"description" => "Return the current user's role and what they are allowed to do in the CMS "
						. "(manage users, tags, settings, templates, modules, callouts), plus the list of things "
						. "the assistant cannot do at any level and where in the admin to do them instead. Use "
						. "this to answer \"what can I do?\" or \"can you do X?\" rather than guessing.",
					"parameters" => [
						"type" => "object",
						"properties" => new \stdClass(),
					],
				],
			];
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$caps = CapabilitySummary::forUser($context->user);

			return AIToolResult::ok([
				"capabilities" => $caps,
				// Level-independent: what the assistant itself can't do, so the model
				// can cite the limit and the admin screen rather than discovering the
				// wall by failing at it mid-conversation.
				"assistant_cannot" => CapabilitySummary::outOfScope(),
				"summary" => CapabilitySummary::promptText($context->user),
			]);
		}
	}
