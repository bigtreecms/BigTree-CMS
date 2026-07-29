<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Developer-only two-phase creation of a *usable* module: the record, its database
	 * table, an add/edit form, a landing view and the actions that go with them.
	 *
	 * Audit #11 C1. `create_module` makes a record with no table — honestly disclosed,
	 * but the single most incomplete thing the assistant can create, and the only tool
	 * whose successful outcome is something broken in the admin navigation. The
	 * endpoint that finishes the job has existed all along (POST /modules/scaffold);
	 * it was declined for running DDL, which is a real concern and the reason this
	 * tool stages the *whole plan* — the table name, every column and its SQL type,
	 * the form, the view, the actions — on the proposal card and runs nothing until a
	 * developer approves it.
	 *
	 * Creating a table is the one write in the catalogue the assistant can't undo:
	 * deleting a module is admin-only, and dropping a table isn't a capability at all.
	 * The summary says so.
	 */
	class ScaffoldModuleTool extends AbstractDeveloperTool {
		/** @var ModuleToolBackend */
		private $backend;

		public function __construct(ModuleToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "scaffold_module";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose creating a complete, usable module (developer only): the module record, its database "
					. "table, an add/edit form and a landing view. Requires approval, and the proposal shows every "
					. "column that would be created. Use this rather than create_module whenever the user wants a "
					. "module they can actually put entries in — create_module makes a record with no table, which "
					. "someone then has to finish in the Module Designer.",
				[
					"type" => "object",
					"properties" => [
						"name" => [
							"type" => "string",
							"description" => "Module name, plural as it should read in the navigation "
								. "(e.g. \"Press Releases\"). Required.",
						],
						"fields" => [
							"type" => "array",
							"description" => "The entry form's fields, in order. Each becomes a form field and a "
								. "database column. Required — a module with no fields has nothing to edit.",
							"items" => [
								"type" => "object",
								"properties" => [
									"title" => [
										"type" => "string",
										"description" => "Field label (e.g. \"Headline\"). The column name is derived "
											. "from it.",
									],
									"type" => [
										"type" => "string",
										"description" => "Field type (e.g. text, textarea, html, image, "
											. "image-reference, list, date). Must be a field type this CMS has — "
											. "get_module_schema on any existing module shows real ones.",
									],
									"subtitle" => [
										"type" => "string",
										"description" => "Optional help text shown under the field's label.",
									],
									"settings" => [
										"type" => "object",
										"description" => "Optional per-field settings (a list field's options, an "
											. "image field's directory, and so on).",
									],
								],
								"required" => ["title", "type"],
							],
						],
						"table" => [
							"type" => "string",
							"description" => "Optional database table name — letters, numbers and underscores only. "
								. "Derived from the module name when omitted, which is usually right.",
						],
						"route" => [
							"type" => "string",
							"description" => "Optional URL route (derived from the name if omitted).",
						],
						"group" => [
							"type" => "string",
							"description" => "Optional module-group id to file the module under.",
						],
						"icon" => [
							"type" => "string",
							"description" => "Optional icon identifier.",
						],
						"view_type" => [
							"type" => "string",
							"description" => "\"searchable\" (the default) for a searchable list, or \"draggable\" "
								. "for a hand-ordered one — which adds a `position` column.",
						],
						"item_title" => [
							"type" => "string",
							"description" => "Singular noun for one entry, used on the form (\"Press Release\"). "
								. "Derived from the module name when omitted.",
						],
						"view_title" => [
							"type" => "string",
							"description" => "Plural noun for the landing view (\"Press Releases\"). Derived from "
								. "the module name when omitted.",
						],
						"actions" => [
							"type" => "object",
							"description" => "Optional workflow toggles. Each one that is true adds its own status "
								. "column to the table and its button to the landing view.",
							"properties" => [
								"approve" => ["type" => "boolean", "description" => "Adds an `approved` column."],
								"feature" => ["type" => "boolean", "description" => "Adds a `featured` column."],
								"archive" => ["type" => "boolean", "description" => "Adds an `archived` column."],
							],
						],
					],
					"required" => ["name", "fields"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["name"] ?? "")) === "") {

				return AIToolResult::error("A module name is required.");
			}

			$validation = $this->backend->aiValidateModuleScaffold($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
