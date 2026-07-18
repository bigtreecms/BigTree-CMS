<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase restore of an archived page — the inverse of archive_page, which
	 * shipped without one, so a page the assistant archived could only be brought
	 * back through the admin UI.
	 *
	 * Publisher-level, like archiving. A page archived only because an ancestor was
	 * archived is refused with a pointer to the parent, since it has no state of its
	 * own to clear.
	 */
	class UnarchivePageTool extends AbstractMutatingTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "unarchive_page";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose restoring an archived page so it is visible on the live site again. Requires publisher "
					. "access and the user's approval.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "integer",
							"description" => "Id of the page to restore (required).",
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ((int)($args["id"] ?? 0) < 1) {

				return AIToolResult::error("A page id is required to unarchive a page.");
			}

			$validation = $this->backend->aiValidatePageUnarchive($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
