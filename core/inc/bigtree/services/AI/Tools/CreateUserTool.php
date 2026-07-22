<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase creation of a basic editor account (administrators). The assistant
	 * never sets a level, permissions, or password — the account is created at level 0
	 * and a password is set out of band. Writes nothing until approved.
	 */
	class CreateUserTool extends AbstractAdminMutatingTool {
		/** @var UserToolBackend */
		private $backend;

		public function __construct(UserToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_user";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose creating a new editor account (administrators). Requires approval. The account is "
					. "created as an editor (level 0) with no elevated permissions and no password — on approval an "
					. "email invite is sent to the address so they can set one. Notification preferences (daily "
					. "digest, alerts) are always left off and cannot be set here.",
				[
					"type" => "object",
					"properties" => [
						"email" => [
							"type" => "string",
							"description" => "The new user's email address (required, must be unique).",
						],
						"name" => [
							"type" => "string",
							"description" => "The user's full name (required — it's what the users list and audit "
								. "entries display).",
						],
						"company" => [
							"type" => "string",
							"description" => "Optional company/organization.",
						],
						"timezone" => [
							"type" => "string",
							"description" => "Optional IANA timezone identifier (e.g. America/New_York).",
						],
					],
					"required" => ["email", "name"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ($context->userLevel() < 1) {

				return AIToolResult::denied("Only administrators can create users.");
			}

			if (trim((string)($args["email"] ?? "")) === "") {

				return AIToolResult::error("An email address is required.");
			}

			$validation = $this->backend->aiValidateUserCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
