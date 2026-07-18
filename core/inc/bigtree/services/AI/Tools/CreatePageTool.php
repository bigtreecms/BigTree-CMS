<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * The flagship two-phase mutating tool: propose the creation of a page.
	 *
	 * Flow (Phase 3):
	 *   1. No parent given → needs_input listing the subtrees this user may actually
	 *      write to (walked server-side; root only for administrators+).
	 *   2. Parent given → validate access, template, and route via the backend. A
	 *      permission problem is denied (the model explains it); a bad argument is a
	 *      recoverable error.
	 *   3. Valid → stage a proposal and return it. Nothing is written yet — the user
	 *      approves the card, and only then does the backend create the page (as a
	 *      live page for a publisher, or a pending change for an editor).
	 */
	class CreatePageTool extends AbstractMutatingTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_page";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose creating a new page. Requires the user's approval before anything is created; "
					. "if you don't know where the page should live, call this without a parent to offer the user "
					. "the locations they can create under.",
				[
					"type" => "object",
					"properties" => [
						"nav_title" => [
							"type" => "string",
							"description" => "Navigation title for the page (required).",
						],
						"parent" => [
							"type" => "integer",
							"description" => "Id of the parent page (0 for the site root). Omit to ask the user where to create it.",
						],
						"title" => [
							"type" => "string",
							"description" => "Optional page (SEO) title; defaults to the nav title.",
						],
						"template" => [
							"type" => "string",
							"description" => "Optional template id for the page. Must be an existing template. "
								. "If omitted, the site's default template is used automatically.",
						],
						"content" => [
							"type" => "object",
							"description" => "Content for the template's fields, keyed by field id "
								. "(e.g. {\"page_header\": \"...\", \"page_content\": \"<p>…</p>\"}). Only simple "
								. "text/html fields can be set. The template's required fields must be filled; "
								. "if you don't know them, call this once without content to be told which fields exist.",
						],
						"in_nav" => [
							"type" => "boolean",
							"description" => "Whether the page appears in navigation (default true).",
						],
						"meta_description" => [
							"type" => "string",
							"description" => "Optional SEO meta description.",
						],
						"meta_keywords" => [
							"type" => "string",
							"description" => "Optional SEO meta keywords.",
						],
						"seo_invisible" => [
							"type" => "boolean",
							"description" => "Whether to hide the page from search engines (default false).",
						],
						"publish_at" => [
							"type" => "string",
							"description" => "Optional date/time the page starts being visible, e.g. \"2026-08-01\" "
								. "or \"2026-08-01 09:00:00\". Omit for immediately.",
						],
						"expire_at" => [
							"type" => "string",
							"description" => "Optional date/time the page stops being visible. Must be after "
								. "publish_at. Omit for never.",
						],
					],
					"required" => ["nav_title"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$nav_title = trim((string)($args["nav_title"] ?? ""));

			if ($nav_title === "") {

				return AIToolResult::error("nav_title required");
			}

			// No parent → ask the user where the page should live, offering only the
			// subtrees they can actually write to.
			if (!array_key_exists("parent", $args) || $args["parent"] === null || $args["parent"] === "") {
				$parents = $this->backend->aiWritableParents($context->user);

				if (!$parents) {

					return AIToolResult::denied(
						"You do not have permission to create pages anywhere in this site.",
						["Ask an administrator to grant page access, or point to an existing page to edit instead."]
					);
				}

				return AIToolResult::needsInput(
					"Where should this page be created?",
					array_map(function (array $p): array {

						return [
							"id" => $p["id"],
							"label" => $p["title"],
							"description" => $p["path"] !== "" ? "/" . $p["path"] : "Top level",
						];
					}, $parents)
				);
			}

			$validation = $this->backend->aiValidatePageCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
