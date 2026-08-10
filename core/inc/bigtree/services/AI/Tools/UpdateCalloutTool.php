<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Developer-only two-phase edit of an existing callout. Hidden from
	 * non-developers; the backend re-checks developer level.
	 *
	 * Adding or fixing a field on an existing callout is the most common developer
	 * ask in this area and previously had no path — the catalog was create-only, so
	 * the assistant could produce a callout it could never amend. Only the keys
	 * supplied are changed; the callout's render file is authored code and is never
	 * rewritten.
	 */
	class UpdateCalloutTool extends AbstractDeveloperTool {
		/** @var CalloutToolBackend */
		private $backend;

		public function __construct(CalloutToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "update_callout";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose editing an existing callout (developer only). Requires approval. Only include the "
					. "properties you want to change. Supplying `fields` REPLACES the callout's whole field list, "
					. "so include every field it should end up with — use get_callout first if you don't know "
					. "them. Fields you carry over keep their existing configuration. The callout's render file "
					. "is not changed.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Id of the callout to edit (required).",
						],
						"name" => [
							"type" => "string",
							"description" => "New human-readable callout name.",
						],
						"description" => [
							"type" => "string",
							"description" => "New description shown to editors.",
						],
						"level" => [
							"type" => "integer",
							"description" => "Minimum admin level allowed to use the callout (0 editor, 1 admin, 2 developer).",
						],
						"group" => [
							"type" => "string",
							"description" => "Callout group (id or name) to move the callout into. A callout belongs to "
								. "at most one group, so this moves it out of any group it is currently in. A page "
								. "region restricted to a group only offers callouts in that group. Use "
								. "create_callout_group if the group doesn't exist yet.",
						],
						"display_field" => [
							"type" => "string",
							"description" => "Field id used as the callout's display label in the page editor. Must be "
								. "one of the ids in the callout's resulting field list.",
						],
						"display_default" => [
							"type" => "string",
							"description" => "Label shown for a callout instance whose display_field is empty.",
						],
						"fields" => [
							"type" => "array",
							"description" => "The callout's complete new field list. Replaces the existing one — any "
								. "field you omit is removed, orphaning its content wherever the callout is placed. "
								. "A field you carry over by id keeps the configuration set in Developer → Callouts, "
								. "so restate only what you are changing.",
							"items" => [
								"type" => "object",
								"properties" => [
									"id" => [
										"type" => "string",
										"description" => "Field id — letters, numbers, underscores and hyphens only (e.g. "
											. "\"headline\"). Used exactly as given: it is the key the field's content is "
											. "stored under.",
									],
									"type" => [
										"type" => "string",
										"description" => "Omit to keep an existing field's type. Changing it discards "
											. "the configuration set for the old type.",
									],
									"title" => ["type" => "string", "description" => "Omit to keep an existing field's label."],
									"subtitle" => ["type" => "string"],
									"options" => [
										"type" => "array",
										"description" => "Choices for a \"list\" field, in order. Strings, or "
											. "{value, description} objects when the stored value differs from the "
											. "label. A list field with no options renders as an empty select — and "
											. "if it is also required, nobody can save the record at all — so this "
											. "is required when type is \"list\". Applies to newly added fields only; "
											. "an existing field keeps the choices set in Developer → Callouts, so "
											. "adding one choice to an existing list is a Developer → Callouts edit.",
										"items" => ["type" => "string"],
									],
									"required" => [
										"type" => "boolean",
										"description" => "Applies to newly added fields only; an existing field keeps "
											. "the validation rules set in Developer → Callouts.",
									],
									"default" => [
										"type" => "string",
										"description" => "Starting value for a newly added field. Applies to newly "
											. "added fields only; an existing field keeps the default set in "
											. "Developer → Callouts.",
									],
								],
								"required" => ["id"],
							],
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["id"] ?? "")) === "") {

				return AIToolResult::error("A callout id is required.");
			}

			$validation = $this->backend->aiValidateCalloutUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
