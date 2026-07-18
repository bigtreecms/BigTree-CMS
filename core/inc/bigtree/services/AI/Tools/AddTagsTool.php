<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase tagging of a page or module entry. Writes nothing until approved.
	 *
	 * Offered at editor level rather than admin, matching what the page editor itself
	 * allows: attaching a tag that already exists needs only edit access on the thing
	 * being tagged. Creating a *new* tag is still administrator-only — the backend
	 * enforces that split (and re-checks it at approval, since a tag that was new at
	 * staging may exist by then), and the preview flags which tags are new.
	 */
	class AddTagsTool extends AbstractMutatingTool {
		/** @var TagToolBackend */
		private $backend;

		public function __construct(TagToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "add_tags";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose adding tags to a page or a module entry. Requires approval. Identify the target with "
					. "either page_id, or module_id + entry_id. Tags that already exist can be attached by any "
					. "editor; creating a brand-new tag requires administrator level.",
				[
					"type" => "object",
					"properties" => [
						"page_id" => [
							"type" => "integer",
							"description" => "Id of the page to tag. Use this or module_id + entry_id, not both.",
						],
						"module_id" => [
							"type" => "string",
							"description" => "Module id or route, when tagging a module entry.",
						],
						"entry_id" => [
							"type" => "integer",
							"description" => "Id of the module entry to tag (with module_id).",
						],
						"tags" => [
							"type" => "array",
							"description" => "Tag names to add.",
							"items" => ["type" => "string"],
						],
					],
					"required" => ["tags"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$has_page = (int)($args["page_id"] ?? 0) > 0;
			$has_entry = trim((string)($args["module_id"] ?? "")) !== "" && (int)($args["entry_id"] ?? 0) > 0;

			if (!$has_page && !$has_entry) {

				return AIToolResult::error("Provide either a page_id, or a module_id and entry_id, to identify "
					. "what to tag.");
			}

			$validation = $this->backend->aiValidateAddTags($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
