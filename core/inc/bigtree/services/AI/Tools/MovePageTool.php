<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase move of a page to a new parent — "move this page under X" is a
	 * natural ask that previously had no tool behind it.
	 *
	 * Moving rewrites the page's URL and the URL of every page beneath it, so the
	 * proposal reports the old and new paths and how many descendants are affected.
	 * Requires publisher access on the page and edit access at the destination.
	 */
	class MovePageTool extends AbstractMutatingTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "move_page";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose moving a page (and everything beneath it) under a different parent page. This changes the "
					. "page's URL and the URLs of all its child pages. Requires publisher access on the page, edit "
					. "access at the destination, and the user's approval.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "integer",
							"description" => "Id of the page to move (required).",
						],
						"parent" => [
							"type" => "integer",
							"description" => "Id of the page to move it under, or 0 for the top level of the site "
								. "(required).",
						],
					],
					"required" => ["id", "parent"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if ((int)($args["id"] ?? 0) < 1) {

				return AIToolResult::error("A page id is required to move a page.");
			}

			if (!array_key_exists("parent", $args)) {

				return AIToolResult::error("A parent is required — the id of the page to move this page under, or 0 "
					. "for the site root.");
			}

			$validation = $this->backend->aiValidatePageMove($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
