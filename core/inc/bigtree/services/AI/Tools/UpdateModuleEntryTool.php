<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase edit of a module entry. The form's text-like fields, its reference
	 * fields (by resource id) and its relationship fields (by entry id) are settable,
	 * and the per-entry group-based-permission check is applied at both validation and
	 * approval. On approval a publisher writes live, an editor submits a pending
	 * change. Writes nothing during the turn.
	 */
	class UpdateModuleEntryTool extends AbstractMutatingTool {
		/** @var ModuleEntryToolBackend */
		private $backend;

		public function __construct(ModuleEntryToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "update_module_entry";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose editing an entry in a module. Requires approval. Provide the module id, the entry id, "
					. "and a \"data\" object of the fields to change.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Id of the module (required).",
						],
						"entry_id" => [
							"type" => "string",
							"description" => "Id of the entry to edit (required). Either the numeric id of a "
								. "published entry, or a \"p\"-prefixed id like \"p12\" for one that is still an "
								. "unpublished draft.",
						],
						"data" => [
							"type" => "object",
							"description" => "Changed field values keyed by column name. Text-like fields take their "
								. "value directly; an image, file or video reference field takes the numeric id of a "
								. "file that is already in the Files library (get_module_entry names the file each "
								. "reference currently points at under entry_references); a relationship field takes a "
								. "list of entry ids (e.g. [\"12\", \"15\"]) — get_module_entry lists the ids each one "
								. "currently holds under related. Each value REPLACES the whole field, so never supply "
								. "a value you only saw in truncated form — get_module_entry lists those in "
								. "fields_truncated.",
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
						"og_title" => [
							"type" => "string",
							"description" => "New Open Graph (social sharing) title for the entry.",
						],
						"og_description" => [
							"type" => "string",
							"description" => "New Open Graph (social sharing) description for the entry.",
						],
						"save_as_draft" => [
							"type" => "boolean",
							"description" => "Queue this edit as a pending change instead of publishing it. Use when the user asks to "
								. "draft the change or have someone review it before it goes live — the live entry is left "
								. "untouched. Without this a publisher's approval goes live immediately; an editor's always "
								. "queues either way.",
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

			if (trim((string)($args["entry_id"] ?? "")) === "") {

				return AIToolResult::error("An entry_id is required.");
			}

			$validation = $this->backend->aiValidateEntryUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
