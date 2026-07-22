<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase edit of a user's profile fields — name, email, company, timezone
	 * (administrators). The assistant never changes a user's level or permissions
	 * (an explicit non-tool) and cannot edit a user who outranks the actor. Writes
	 * nothing until approved.
	 */
	class UpdateUserTool extends AbstractAdminMutatingTool {
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
				"Propose editing a user's profile (administrators): name, email, company, or timezone. Requires "
					. "approval. This never changes a user's level or permissions.",
				[
					"type" => "object",
					"properties" => [
						"user_id" => [
							"type" => "integer",
							"description" => "Id of the user to edit (required).",
						],
						"name" => [
							"type" => "string",
							"description" => "New full name (cannot be blanked).",
						],
						"email" => [
							"type" => "string",
							"description" => "New email address (must be unique).",
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
							"description" => "Content-alert thresholds: page id => number of days after which that "
								. "page (and everything under it) is flagged stale for this user. Replaces the whole "
								. "map — read the current one with get_content_alerts first.",
						],
					],
					"required" => ["user_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ($context->userLevel() < 1) {

				return AIToolResult::denied("Only administrators can edit users.");
			}

			if ((int)($args["user_id"] ?? 0) < 1) {

				return AIToolResult::error("A user_id is required.");
			}

			$validation = $this->backend->aiValidateUserUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
