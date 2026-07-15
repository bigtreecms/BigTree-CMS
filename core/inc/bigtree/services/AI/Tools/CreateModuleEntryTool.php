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
