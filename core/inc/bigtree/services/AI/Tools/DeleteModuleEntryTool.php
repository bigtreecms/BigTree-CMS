<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase permanent deletion of a module entry.
	 *
	 * The only destructive tool in the catalog, so the proposal names the entry
	 * rather than just its id — approving "delete entry #418" sight-unseen is exactly
	 * the failure mode the two-phase flow exists to prevent. Publisher-only on the
	 * row, and the approved write reuses the REST delete's resource deallocation so
	 * no orphaned allocations are left behind.
	 */
	class DeleteModuleEntryTool extends AbstractMutatingTool {
		/** @var ModuleEntryToolBackend */
		private $backend;

		public function __construct(ModuleEntryToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "delete_module_entry";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose permanently deleting a module entry. This cannot be undone. Requires publisher access on "
					. "the entry and the user's approval. Prefer set_module_entry_flag with flag=archived when the "
					. "user just wants the entry hidden.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Module id or route (required).",
						],
						"entry_id" => [
							"type" => "integer",
							"description" => "Id of the entry to delete (required).",
						],
						"form" => [
							"type" => "string",
							"description" => "Optional form id, for a module that has more than one entry form. "
								. "If the module has several and you omit this, you'll be asked which to use.",
						],
					],
					"required" => ["module_id", "entry_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["module_id"] ?? "")) === "") {

				return AIToolResult::error("A module_id is required.");
			}

			if ((int)($args["entry_id"] ?? 0) < 1) {

				return AIToolResult::error("An entry_id is required.");
			}

			$validation = $this->backend->aiValidateEntryDelete($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
