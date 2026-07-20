<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase restore of a page revision — the "undo that" the user actually means
	 * when a page has been changed wrongly.
	 *
	 * Publisher-only, unlike the other page-mutating tools: a restore replaces the
	 * live page's content columns outright and has no pending-change form, so an
	 * editor cannot stage one. The proposal card shows which content columns the
	 * restore would change, and the current version is snapshotted at approval so the
	 * restore is itself reversible.
	 */
	class RestorePageRevisionTool extends AbstractMutatingTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "restore_page_revision";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose restoring a page to one of its earlier revisions — use this for \"undo the change to "
					. "this page\". Requires approval, and publisher access on the page, because a restore "
					. "replaces the live content immediately. Call get_page_revisions first to find the "
					. "revision id.",
				[
					"type" => "object",
					"properties" => [
						"page_id" => [
							"type" => "integer",
							"description" => "Id of the page to restore (required).",
						],
						"revision_id" => [
							"type" => "integer",
							"description" => "Id of the revision to restore, from get_page_revisions (required).",
						],
					],
					"required" => ["page_id", "revision_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ((int)($args["page_id"] ?? 0) < 1 || (int)($args["revision_id"] ?? 0) < 1) {

				return AIToolResult::error("Both a page_id and a revision_id are required. Use get_page_revisions "
					. "to list a page's revisions.");
			}

			$validation = $this->backend->aiValidateRevisionRestore($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
