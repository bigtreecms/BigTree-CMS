<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\CapabilitySummary;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase edit of a user's profile fields — name, email, company, timezone,
	 * daily digest, content alerts. The assistant never changes a user's level or
	 * permissions (an explicit non-tool) and cannot edit a user who outranks the
	 * actor. Writes nothing until approved.
	 *
	 * Offered at every level since audit #14 B1, which is why this no longer extends
	 * AbstractAdminMutatingTool: `PATCH /users/{id}` is
	 * `["any" => [["level" => 1], ["self" => "id"]]]` and the SPA's Profile screen is
	 * that route, so an editor changing their own notification preferences is an
	 * ordinary thing REST has always allowed. Hiding the tool from them made the
	 * assistant stricter than REST with no decline line to explain it — "turn off my
	 * daily digest" got an editor told to ask an administrator for two clicks they
	 * could do themselves. The backend, not the registry, is what separates the two
	 * cases: UserService::aiValidateUserUpdate denies a level-0 caller any id but
	 * their own, and excludes `email` from the self path.
	 */
	class UpdateUserTool extends AbstractMutatingTool {
		/** @var UserToolBackend */
		private $backend;

		public function __construct(UserToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "update_user";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose editing a user's profile: name, company, timezone, the daily content digest, and "
					. "content-alert subscriptions. Any user, at any permission level, may edit their OWN profile "
					. "this way — pass their own user id, which is named in your instructions and returned by "
					. "get_my_capabilities. Editing SOMEONE ELSE's profile requires administrator level, and email "
					. "address can only be changed for someone else (never your own — that is your sign-in "
					. "identity). Requires approval. This never changes a user's level or permissions.",
				[
					"type" => "object",
					"properties" => [
						"user_id" => [
							"type" => "integer",
							"description" => "Id of the user to edit (required). Use the signed-in user's own id for "
								. "\"my profile\", \"my timezone\", \"my alerts\".",
						],
						"name" => [
							"type" => "string",
							"description" => "New full name (cannot be blanked).",
						],
						"email" => [
							"type" => "string",
							"description" => "New email address (must be unique). Administrators only, and never for "
								. "the signed-in user's own account.",
						],
						"company" => [
							"type" => "string",
							"description" => "New company/organization.",
						],
						"timezone" => [
							"type" => "string",
							"description" => "New IANA timezone identifier (e.g. America/New_York).",
						],
						"daily_digest" => [
							"type" => "boolean",
							"description" => "Whether this user receives the daily content digest email.",
						],
						"alerts" => [
							"type" => "object",
							"description" => "Content-alert subscriptions: page id => true to watch that page (and "
								. "everything under it), false to stop watching it. Use page id 0 for the whole page "
								. "tree. Merged into the user's existing subscriptions, so send only the pages you "
								. "are changing. How stale is too stale isn't set here — it comes from each page's "
								. "own max_age.",
						],
					],
					"required" => ["user_id"],
				]
			);
		}

		public function isAvailable($user): bool {

			return true;
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$id = (int)($args["user_id"] ?? 0);

			if ($id < 1) {

				return AIToolResult::error("A user_id is required.");
			}

			// The level gate lives in the backend now (it is the only place that knows
			// whether this id is the caller's own), but the tool still refuses the
			// clearly-wrong case up front so an editor aiming at somebody else never
			// reaches a database read.
			if ($context->userLevel() < 1 && $id !== CapabilitySummary::userId($context->user)) {

				return AIToolResult::denied("Only administrators can edit other users. You can change your own "
					. "profile — your user id is " . CapabilitySummary::userId($context->user) . ".");
			}

			$validation = $this->backend->aiValidateUserUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
