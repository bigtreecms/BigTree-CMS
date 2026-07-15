<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase archive of a page (and its descendants). Archiving hides a page from
	 * the live site; it is a publish-level action, so an editor cannot even stage it
	 * as a pending change — the validation denies it up front.
	 *
	 * Nothing is archived during the turn: the tool stages a proposal and the backend
	 * archives live only when the user approves. This is deliberately archive-only
	 * (never permanent deletion, which is an explicit non-tool).
	 */
	class ArchivePageTool extends AbstractMutatingTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "archive_page";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose archiving a page so it is hidden from the live site (it can be unarchived later). "
					. "Requires publisher access and the user's approval. This never permanently deletes anything.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "integer",
							"description" => "Id of the page to archive (required).",
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ((int)($args["id"] ?? 0) < 1) {

				return AIToolResult::error("A page id is required to archive a page.");
			}

			$validation = $this->backend->aiValidatePageArchive($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
