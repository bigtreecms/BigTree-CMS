<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase edit of an existing page's plain content fields (nav title, page
	 * title, meta description/keywords, navigation visibility, template, route,
	 * publish/expire scheduling, external-link target, and the scalar Open Graph
	 * fields).
	 *
	 * Like every mutating tool it writes nothing during the turn: it validates the
	 * requested change against the live page and the user's edit access, stages a
	 * proposal describing the field-level diff, and returns it. On approval the
	 * backend publishes live for a publisher or queues an EDIT pending change.
	 *
	 * Resource (template field) editing is intentionally out of scope — the assistant
	 * only touches the page's metadata.
	 */
	class UpdatePageTool extends AbstractMutatingTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "update_page";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose editing an existing page's metadata. Requires the user's approval before anything "
					. "changes. Only include the fields you want to change.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Id of the page to edit (required). Use the numeric id of a live page, or a "
								. "\"p\"-prefixed id (like \"p12\") to edit a page that is still an unpublished "
								. "draft — get_pending_changes lists those.",
						],
						"nav_title" => [
							"type" => "string",
							"description" => "New navigation title.",
						],
						"title" => [
							"type" => "string",
							"description" => "New page (SEO) title.",
						],
						"meta_description" => [
							"type" => "string",
							"description" => "New meta description.",
						],
						"meta_keywords" => [
							"type" => "string",
							"description" => "New meta keywords.",
						],
						"in_nav" => [
							"type" => "boolean",
							"description" => "Whether the page appears in navigation.",
						],
						"seo_invisible" => [
							"type" => "boolean",
							"description" => "Whether the page is hidden from search engines.",
						],
						"template" => [
							"type" => "string",
							"description" => "New template id (must be an existing template).",
						],
						"route" => [
							"type" => "string",
							"description" => "New URL route segment for the page.",
						],
						"publish_at" => [
							"type" => "string",
							"description" => "Date the page should go live, e.g. \"2026-08-01\" or "
								. "\"2026-08-01 09:00:00\". Pass an empty string to clear the scheduled date.",
						],
						"expire_at" => [
							"type" => "string",
							"description" => "Date the page should stop being published. Must be after publish_at. "
								. "Pass an empty string to clear it.",
						],
						"external" => [
							"type" => "string",
							"description" => "Make this a navigation link to another site: a full http:// or https:// "
								. "URL. A page is either a normal page with a template or an external link, never "
								. "both — clear the template when setting this. Pass an empty string to turn a link "
								. "back into a normal page (you must supply a template in the same edit).",
						],
						"new_window" => [
							"type" => "boolean",
							"description" => "For an external link, whether it opens in a new window.",
						],
						"og_title" => [
							"type" => "string",
							"description" => "Open Graph (social sharing) title. Empty string clears it.",
						],
						"og_description" => [
							"type" => "string",
							"description" => "Open Graph (social sharing) description. Empty string clears it.",
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["id"] ?? "")) === "") {

				return AIToolResult::error("A page id is required to edit a page.");
			}

			$validation = $this->backend->aiValidatePageUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
