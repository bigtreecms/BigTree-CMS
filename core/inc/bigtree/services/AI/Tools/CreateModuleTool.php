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
					. "record only — no database table, form, view or actions — so the result is not usable "
					. "until those are added. Prefer scaffold_module, which proposes the table, form and landing "
					. "view along with the record; use this one only when the module genuinely is a bare record.",
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
							"description" => "Optional icon slug for the admin navigation. Must be one of the fixed vocabulary get_module returns as `icon_options` — anything else is refused. Omit for no icon.",
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
