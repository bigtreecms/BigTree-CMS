<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Developer-only two-phase creation of a module. Creates a bare module record only
	 * — it never scaffolds a database table (that runs irreversible DDL and belongs in
	 * the Module Designer). Hidden from non-developers; writes nothing until approved.
	 */
	class CreateModuleTool extends AbstractDeveloperTool {
		/** @var ModuleToolBackend */
		private $backend;

		public function __construct(ModuleToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_module";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose creating a new module (developer only). Requires approval. This creates the module "
					. "record only — it does not create a database table; use the Module Designer for that.",
				[
					"type" => "object",
					"properties" => [
						"name" => [
							"type" => "string",
							"description" => "Module name (required).",
						],
						"route" => [
							"type" => "string",
							"description" => "Optional URL route (derived from the name if omitted).",
						],
						"group" => [
							"type" => "string",
							"description" => "Optional module-group id to file the module under.",
						],
						"class" => [
							"type" => "string",
							"description" => "Optional module class name.",
						],
						"icon" => [
							"type" => "string",
							"description" => "Optional icon identifier.",
						],
					],
					"required" => ["name"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["name"] ?? "")) === "") {

				return AIToolResult::error("A module name is required.");
			}

			$validation = $this->backend->aiValidateModuleCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
