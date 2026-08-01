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
						"route" => [
							"type" => "string",
							"description" => "Optional URL segment for the page (e.g. \"results\"). Defaults to a "
								. "slug derived from the nav title, and is uniquified against its siblings either way.",
						],
						"template" => [
							"type" => "string",
							"description" => "Optional template id for the page. Must be an existing template. "
								. "If omitted, the site's default template is used automatically.",
						],
						"content" => [
							"type" => "object",
							"description" => "Content for the template's fields, keyed by field id "
								. "(e.g. {\"page_header\": \"...\", \"page_content\": \"<p>…</p>\"}). Text-like fields "
								. "take their value directly; an image, file or video reference field takes the "
								. "numeric id of a file that is already in the Files library (find one with "
								. "search_files or list_resources); a relationship field takes a list of entry ids "
								. "(e.g. [\"12\", \"15\"]). The template's required fields must be filled; "
								. "if you don't know them, call this once without content to be told which fields exist.",
							// A relationship field's value is a list of ids, so a string is
							// not the only shape this object carries (audit #11 B2).
							"additionalProperties" => [
								"anyOf" => [
									["type" => "string"],
									["type" => "array", "items" => ["type" => "string"]],
								],
							],
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
						"max_age" => [
							"type" => "integer",
							"description" => "Days after which the page is flagged as stale on the dashboard's "
								. "content alerts. 0 (the default) never flags it.",
						],
						// No absolute example here on purpose (audit #14 A1): the old one
						// steered the model into computing a date from its own sense of
						// "now", which is its training cutoff rather than this server's
						// clock. The relative form is resolved server-side, correctly.
						"publish_at" => [
							"type" => "string",
							"description" => "Optional date/time the page starts being visible. Omit for immediately. "
								. "Pass the user's own words for a relative date (\"next Monday\", \"in two weeks\") "
								. "— they are resolved against this site's clock and the resolved date is shown on "
								. "the confirmation card. Never compute an absolute date yourself.",
						],
						"expire_at" => [
							"type" => "string",
							"description" => "Optional date/time the page stops being visible. Must be after "
								. "publish_at. Omit for never. Relative dates are resolved server-side, as for "
								. "publish_at.",
						],
						"tags" => [
							"type" => "array",
							"description" => "Optional tag names to attach to the new page. Tags that already exist "
								. "can be used by any editor; creating a brand-new tag requires administrator level.",
							"items" => ["type" => "string"],
						],
						"external" => [
							"type" => "string",
							"description" => "Make this a navigation link to another site instead of a real page: a "
								. "full http:// or https:// URL. Leave the template unset when using this — a page is "
								. "either a normal page with a template or an external link, never both.",
						],
						"new_window" => [
							"type" => "boolean",
							"description" => "For an external link, whether it opens in a new window.",
						],
						"og_title" => [
							"type" => "string",
							"description" => "Optional Open Graph (social sharing) title.",
						],
						"og_description" => [
							"type" => "string",
							"description" => "Optional Open Graph (social sharing) description.",
						],
						"save_as_draft" => [
							"type" => "boolean",
							"description" => "Save this as a draft in the pending queue instead of publishing it. Use when the user "
								. "asks to draft the page or have someone review it before it goes live. Without this a "
								. "publisher's approval goes live immediately; an editor's always queues either way.",
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
				$writable = $this->backend->aiWritableParents($context->user);
				$parents = $writable["parents"] ?? [];

				if (!$parents) {

					return AIToolResult::denied(
						"You do not have permission to create pages anywhere in this site.",
						["Ask an administrator to grant page access, or point to an existing page to edit instead."]
					);
				}

				// The top-level list is capped; when it is partial, say so rather than
				// present a truncated choice list as the whole set (audit #7 D2).
				$prompt = !empty($writable["has_more"])
					? "Where should this page be created? This is a partial list — if you don't see the "
						. "right parent, name the page it should live under and I'll use that."
					: "Where should this page be created?";

				return AIToolResult::needsInput(
					$prompt,
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
