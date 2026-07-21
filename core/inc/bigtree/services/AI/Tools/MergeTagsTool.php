<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase merge of duplicate tags.
	 *
	 * add_tags/remove_tags manage which tags a record carries; this manages the tag
	 * vocabulary itself. Tag hygiene ("we have both 'e-mail' and 'email'") is a
	 * natural language task, and the capability summary already implied the
	 * assistant could do it. Administrator-only, and destructive — the merged tags
	 * are deleted — so the card states how many records move.
	 */
	class MergeTagsTool extends AbstractAdminMutatingTool {
		/** @var TagToolBackend */
		private $backend;

		public function __construct(TagToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "merge_tags";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose merging one or more duplicate tags into a single tag (administrator only). Requires "
					. "approval. Every record tagged with the merged tags is re-tagged with the target, and the "
					. "merged tags are deleted — this can't be undone. Use search_tags first to confirm the names.",
				[
					"type" => "object",
					"properties" => [
						"into" => [
							"type" => "string",
							"description" => "The tag to keep — its name, or its numeric id (required).",
						],
						"from" => [
							"type" => "array",
							"description" => "The tag(s) to merge in and delete — names or numeric ids (required).",
							"items" => ["type" => "string"],
						],
					],
					"required" => ["into", "from"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["into"] ?? "")) === "" || !(array)($args["from"] ?? [])) {

				return AIToolResult::error("Both `into` (the tag to keep) and `from` (the tags to merge in) are "
					. "required.");
			}

			$validation = $this->backend->aiValidateTagMerge($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
