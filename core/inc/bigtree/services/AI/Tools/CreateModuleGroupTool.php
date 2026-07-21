<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase creation of a module group.
	 *
	 * create_module and update_module both accept a group but required it to already
	 * exist, with no path to make one — so "put this in a Content group" dead-ended.
	 * Developer-only, like every module write; the group starts empty and modules are
	 * moved into it with update_module.
	 */
	class CreateModuleGroupTool extends AbstractDeveloperTool {
		/** @var ModuleToolBackend */
		private $backend;

		public function __construct(ModuleToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_module_group";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose creating a new module group (developer only). Requires approval. Module groups organise "
					. "the admin navigation. The group is created empty; use create_module or update_module with "
					. "its name to put modules in it.",
				[
					"type" => "object",
					"properties" => [
						"name" => [
							"type" => "string",
							"description" => "Name of the group, e.g. \"Content\" (required).",
						],
					],
					"required" => ["name"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["name"] ?? "")) === "") {

				return AIToolResult::error("A module group name is required.");
			}

			$validation = $this->backend->aiValidateModuleGroupCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
