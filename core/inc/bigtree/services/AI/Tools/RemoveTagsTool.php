<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase removal of tags from a page or module entry — the inverse of
	 * add_tags, which shipped without one.
	 *
	 * Detaching a tag never deletes the tag itself (other content may still use it,
	 * and deleting tags is not an assistant action), so this needs only edit access
	 * on the thing being untagged. Tags that aren't actually attached are reported
	 * and ignored rather than failing the whole call.
	 */
	class RemoveTagsTool extends AbstractMutatingTool {
		/** @var TagToolBackend */
		private $backend;

		public function __construct(TagToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "remove_tags";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose removing tags from a page or a module entry. Requires approval. Identify the target with "
					. "either page_id, or module_id + entry_id. This only detaches the tags — the tags themselves "
					. "continue to exist for other content.",
				[
					"type" => "object",
					"properties" => [
						"page_id" => [
							"type" => "integer",
							"description" => "Id of the page to untag. Use this or module_id + entry_id, not both.",
						],
						"module_id" => [
							"type" => "string",
							"description" => "Module id or route, when untagging a module entry.",
						],
						"entry_id" => [
							"type" => "integer",
							"description" => "Id of the module entry to untag (with module_id).",
						],
						"tags" => [
							"type" => "array",
							"description" => "Tag names to remove.",
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
					. "what to untag.");
			}

			$validation = $this->backend->aiValidateRemoveTags($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
