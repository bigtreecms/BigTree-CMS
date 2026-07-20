<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase toggle of a module entry's archived / featured / approved flag —
	 * the three legacy boolean columns the module views expose as actions.
	 *
	 * One tool rather than three, since they share a gate (publisher on the row) and
	 * differ only in which column they write. Modules whose table lacks the column
	 * are refused at validation with a plain message.
	 */
	class SetModuleEntryFlagTool extends AbstractMutatingTool {
		/** @var ModuleEntryToolBackend */
		private $backend;

		public function __construct(ModuleEntryToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "set_module_entry_flag";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose archiving/restoring, featuring/un-featuring, or approving/un-approving a module entry. "
					. "Requires publisher access on the entry and the user's approval. Archiving hides an entry "
					. "without deleting it — prefer it to delete_module_entry.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Module id or route (required).",
						],
						"entry_id" => [
							"type" => "string",
							"description" => "Id of the entry to change (required). Either the numeric id of a "
								. "published entry, or a \"p\"-prefixed id like \"p12\" for one that is still an "
								. "unpublished draft.",
						],
						"flag" => [
							"type" => "string",
							"enum" => ["archived", "featured", "approved"],
							"description" => "Which flag to set (required).",
						],
						"value" => [
							"type" => "boolean",
							"description" => "true to set the flag, false to clear it (required).",
						],
						"form" => [
							"type" => "string",
							"description" => "Optional form id, for a module that has more than one entry form. "
								. "If the module has several and you omit this, you'll be asked which to use.",
						],
					],
					"required" => ["module_id", "entry_id", "flag", "value"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["module_id"] ?? "")) === "") {

				return AIToolResult::error("A module_id is required.");
			}

			if (trim((string)($args["entry_id"] ?? "")) === "") {

				return AIToolResult::error("An entry_id is required.");
			}

			$validation = $this->backend->aiValidateEntryFlag($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
