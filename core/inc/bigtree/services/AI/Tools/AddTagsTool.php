<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase tagging of a page (administrators). Any tag that does not yet exist is
	 * created as part of the approval, so the preview flags which tags are new. The
	 * user must also have edit access to the page. Writes nothing until approved.
	 */
	class AddTagsTool extends AbstractAdminMutatingTool {
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
				"Propose adding tags to a page (administrators). Requires approval. Tags that don't exist yet "
					. "will be created.",
				[
					"type" => "object",
					"properties" => [
						"page_id" => [
							"type" => "integer",
							"description" => "Id of the page to tag (required).",
						],
						"tags" => [
							"type" => "array",
							"description" => "Tag names to add.",
							"items" => ["type" => "string"],
						],
					],
					"required" => ["page_id", "tags"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ($context->userLevel() < 1) {

				return AIToolResult::denied("Only administrators can manage tags.");
			}

			if ((int)($args["page_id"] ?? 0) < 1) {

				return AIToolResult::error("A page id is required to add tags.");
			}

			$validation = $this->backend->aiValidateAddTags($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
