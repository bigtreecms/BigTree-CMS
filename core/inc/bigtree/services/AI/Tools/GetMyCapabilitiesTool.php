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
	 *
	 * Since audit #14 B2 it is also the *identity* read, which the catalog had nowhere
	 * at all: the payload carried level and role and never a user id, name or email,
	 * and search_users is administrator-gated (audit #1), so an editor could not even
	 * look themselves up. "Which drafts are mine", "did I make that change", "what am
	 * I subscribed to", "use my name in the byline" were unanswerable or answerable
	 * only by guessing. No new exposure: this is the caller's own record — the same
	 * data GET /users/me hands back at level 0 today.
	 */
	class GetMyCapabilitiesTool implements AIToolInterface {
		/** @var UserToolBackend */
		private $backend;

		public function __construct(UserToolBackend $backend) {
			$this->backend = $backend;
		}

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
					"description" => "Return who the signed-in user is (user id, name, email, timezone), their role "
						. "and what they are allowed to do in the CMS (manage users, tags, settings, templates, "
						. "modules, callouts), plus the list of things the assistant cannot do at any level and "
						. "where in the admin to do them instead. Use this to answer \"what can I do?\", \"can you "
						. "do X?\", or anything needing the user's own id, name, email or timezone (\"my drafts\", "
						. "\"my alerts\", \"sign it with my name\") rather than guessing.",
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
				// The two editor-writable profile fields the request user object doesn't
				// carry, so "is my daily digest on?" has an answer and update_user's
				// arguments all have a read surface an editor can reach (audit #14 B2).
				"profile" => $this->backend->aiMyProfile($context->user),
				// Level-independent: what the assistant itself can't do, so the model
				// can cite the limit and the admin screen rather than discovering the
				// wall by failing at it mid-conversation.
				"assistant_cannot" => CapabilitySummary::outOfScope(),
				"summary" => CapabilitySummary::promptText($context->user),
			]);
		}
	}
