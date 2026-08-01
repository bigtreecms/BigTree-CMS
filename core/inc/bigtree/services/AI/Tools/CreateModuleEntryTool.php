<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase creation of a module entry. The form's text-like fields, its
	 * reference fields (by resource id) and its relationship fields (by entry id) can
	 * be set; the backend rejects composite fields and (when called without data)
	 * lists the settable fields so the model can fill them in. On approval a publisher
	 * writes live, an editor queues a pending entry. Writes nothing during the turn.
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
							"description" => "Field values keyed by column name. Text-like fields take their value directly; an "
								. "image, file or video reference field takes the numeric id of a file that is already in "
								. "the Files library (find one with search_files or list_resources); a relationship field "
								. "takes a list of entry ids in the field's own table (e.g. [\"12\", \"15\"]) — find them "
								. "with get_relation_options, which lists exactly that field's linkable rows. "
								. "A date, datetime or time field takes the user's own words for a relative date "
								. "(\"next Monday\") — they are resolved against this site's clock, so never compute "
								. "an absolute date yourself. Composite fields — matrices, "
								. "callouts, media galleries — can't be set here.",
							// A relationship field's value is a list of ids, so a string is
							// not the only shape this object carries (audit #11 B2).
							"additionalProperties" => [
								"anyOf" => [
									["type" => "string"],
									["type" => "array", "items" => ["type" => "string"]],
								],
							],
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
