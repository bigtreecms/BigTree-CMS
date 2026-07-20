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
					. "them. The callout's render file is not changed.",
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
								. "field you omit is removed, orphaning its content wherever the callout is placed.",
							"items" => [
								"type" => "object",
								"properties" => [
									"id" => ["type" => "string"],
									"type" => ["type" => "string"],
									"title" => ["type" => "string"],
									"subtitle" => ["type" => "string"],
								],
								"required" => ["id", "type", "title"],
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
