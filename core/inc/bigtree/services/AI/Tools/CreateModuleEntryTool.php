<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase creation of a module entry. Only the module form's simple scalar
	 * fields can be set; the backend rejects complex fields and (when called without
	 * data) lists the settable fields so the model can fill them in. On approval a
	 * publisher writes live, an editor queues a pending entry. Writes nothing during
	 * the turn.
	 */
	class CreateModuleEntryTool extends AbstractMutatingTool {
		/** @var ModuleEntryToolBackend */
		private $backend;

		public function __construct(ModuleEntryToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_module_entry";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose adding an entry to a module. Requires approval. Provide the module id and a \"data\" "
					. "object of field values; call it without data first if you need to learn the module's fields.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Id of the module to add an entry to (required).",
						],
						"data" => [
							"type" => "object",
							"description" => "Field values keyed by column name. Only the module form's simple fields can be set.",
						],
						"form" => [
							"type" => "string",
							"description" => "Optional form id, for a module that has more than one entry form. "
								. "If the module has several and you omit this, you'll be asked which to use.",
						],
						"tags" => [
							"type" => "array",
							"description" => "Optional tag names to attach to the new entry. Tags that already exist "
								. "can be used by any editor; creating a brand-new tag requires administrator level.",
							"items" => ["type" => "string"],
						],
						"og_title" => [
							"type" => "string",
							"description" => "Optional Open Graph (social sharing) title.",
						],
						"og_description" => [
							"type" => "string",
							"description" => "Optional Open Graph (social sharing) description.",
						],
						"save_as_draft" => [
							"type" => "boolean",
							"description" => "Save this as a draft in the pending queue instead of publishing it. Use when the user "
								. "asks to draft the entry or have someone review it before it goes live. Without this a "
								. "publisher's approval goes live immediately; an editor's always queues either way.",
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

			$validation = $this->backend->aiValidateEntryCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
