<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase bookmarking of a page's current content as a named revision.
	 *
	 * The catalog could restore a revision but never create one, so "save where this
	 * is before you rewrite it" had no path — the safety net existed only if someone
	 * had already thought to hang it. Publisher-only, matching the REST route and
	 * restore_page_revision. Nothing on the live page changes.
	 */
	class SavePageRevisionTool extends AbstractMutatingTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "save_page_revision";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose saving the page's current content as a named revision, so it can be restored later. "
					. "Requires approval and publisher access. Use this before a risky rewrite — it changes "
					. "nothing on the live page, it only bookmarks what's there now.",
				[
					"type" => "object",
					"properties" => [
						"page_id" => [
							"type" => "integer",
							"description" => "Id of the page to snapshot (required).",
						],
						"description" => [
							"type" => "string",
							"description" => "Short label for the revision, e.g. \"before the pricing rewrite\" "
								. "(required — it's what tells this revision apart from automatic snapshots).",
						],
					],
					"required" => ["page_id", "description"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ((int)($args["page_id"] ?? 0) < 1) {

				return AIToolResult::error("A page_id is required.");
			}

			$validation = $this->backend->aiValidateSaveRevision($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
