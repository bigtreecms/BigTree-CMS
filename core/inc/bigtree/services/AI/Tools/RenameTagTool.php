<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase rename of a tag record.
	 *
	 * The other half of tag hygiene alongside merge_tags. A rename onto a name that
	 * already exists is a merge in disguise, so the backend refuses it and points at
	 * merge_tags rather than leaving two tags sharing a name. Administrator-only,
	 * like tag creation, because it changes the site's shared vocabulary.
	 */
	class RenameTagTool extends AbstractAdminMutatingTool {
		/** @var TagToolBackend */
		private $backend;

		public function __construct(TagToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "rename_tag";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose renaming a tag (administrator only). Requires approval. Every record carrying the tag "
					. "keeps it under the new name, and the tag's public URL changes. If a tag with the new name "
					. "already exists, use merge_tags instead.",
				[
					"type" => "object",
					"properties" => [
						"tag" => [
							"type" => "string",
							"description" => "The tag to rename — its current name, or its numeric id (required).",
						],
						"name" => [
							"type" => "string",
							"description" => "The new name (required). Letters, numbers and spaces.",
						],
					],
					"required" => ["tag", "name"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["tag"] ?? "")) === "" || trim((string)($args["name"] ?? "")) === "") {

				return AIToolResult::error("Both `tag` (the tag to rename) and `name` (the new name) are required.");
			}

			$validation = $this->backend->aiValidateTagRename($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
