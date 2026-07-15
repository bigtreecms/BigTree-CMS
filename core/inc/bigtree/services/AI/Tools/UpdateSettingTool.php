<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase change of a setting's value (administrators only). Refuses internal,
	 * encrypted, and locked settings — the assistant never handles secrets. Stages a
	 * proposal showing the old → new value and writes nothing until approved.
	 */
	class UpdateSettingTool extends AbstractAdminMutatingTool {
		/** @var SettingToolBackend */
		private $backend;

		public function __construct(SettingToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "update_setting";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose changing the value of a setting (administrators). Requires approval. Cannot change "
					. "internal, encrypted, or locked settings.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Id of the setting to change (required).",
						],
						"value" => [
							"description" => "The new value (a string, number, boolean, or structured value as the setting expects). Required.",
						],
					],
					"required" => ["id", "value"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ($context->userLevel() < 1) {

				return AIToolResult::denied("Only administrators can change settings.");
			}

			if (trim((string)($args["id"] ?? "")) === "") {

				return AIToolResult::error("A setting id is required.");
			}

			$validation = $this->backend->aiValidateSettingUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
