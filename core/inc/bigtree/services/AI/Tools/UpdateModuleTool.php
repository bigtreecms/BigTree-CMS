<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Developer-only two-phase edit of a module's presentation: name, group, icon.
	 *
	 * Deliberately narrow. The route is excluded — changing it breaks every
	 * bookmarked admin URL and any hard-coded link — and the module's table, forms,
	 * views and actions belong to the Module Designer, whose changes run DDL and stay
	 * a documented decline. What's left is the plain-text edit developers actually
	 * ask for: renaming a module or moving it into a different group.
	 */
	class UpdateModuleTool extends AbstractDeveloperTool {
		/** @var ModuleToolBackend */
		private $backend;

		public function __construct(ModuleToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "update_module";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose renaming a module, moving it to a different group, changing its icon, or pointing it at "
					. "a different handler class (developer only). Requires approval. Only include what you want to "
					. "change. A module's route, database table, forms, views and actions cannot be changed here — "
					. "those live in the Module Designer.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Module id or route (required).",
						],
						"name" => [
							"type" => "string",
							"description" => "New human-readable module name.",
						],
						"group" => [
							"type" => "string",
							"description" => "Id of an existing module group to move the module into. Pass an empty "
								. "string to remove it from its group.",
						],
						"icon" => [
							"type" => "string",
							"description" => "Optional icon slug for the admin navigation. Must be one of the fixed vocabulary get_module returns as `icon_options` — anything else is refused. Omit for no icon.",
						],
						"class" => [
							"type" => "string",
							"description" => "Name of the module class that handles this module's entries. It must "
								. "already exist and not belong to another module — the Module Designer creates "
								. "classes, this only re-points an existing one. Pass an empty string to unset it.",
						],
					],
					"required" => ["module_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["module_id"] ?? "")) === "") {

				return AIToolResult::error("A module_id is required.");
			}

			$validation = $this->backend->aiValidateModuleUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
