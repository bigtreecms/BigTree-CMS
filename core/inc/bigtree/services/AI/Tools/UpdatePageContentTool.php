<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase edit of a page's actual content — the fields defined by its template.
	 *
	 * update_page covers a page's metadata; this covers its body. Only the template's
	 * simple text/HTML fields can be set (never uploads, images, matrices or
	 * callouts), and the supplied content is merged onto what the page already has,
	 * so editing one field never blanks the others.
	 *
	 * Can also switch the page's template in the same proposal, which is the safe way
	 * to do it: the incoming template's required content is supplied in the very same
	 * edit rather than left empty.
	 */
	class UpdatePageContentTool extends AbstractMutatingTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "update_page_content";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose editing the content of an existing page — the fields defined by its template (body copy, "
					. "headings, intro text). Requires the user's approval before anything changes. Only include "
					. "the fields you want to change; everything else is left as-is. Call with no `content` to be "
					. "told which fields the page's template has. Use update_page instead for metadata like the "
					. "navigation title or meta description.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Id of the page whose content to edit (required). Use the numeric id of a live page, or a "
								. "\"p\"-prefixed id (like \"p12\") to edit a page that is still an unpublished "
								. "draft — get_pending_changes lists those.",
						],
						"content" => [
							"type" => "object",
							"description" => "The template fields to change, keyed by field id "
								. "(e.g. {\"page_content\": \"<p>…</p>\"}). Text-like fields take their value "
								. "directly; an image, file or video reference field takes the numeric id of a file "
								. "that is already in the Files library (get_page names the file each reference "
								. "currently points at under content_references); a relationship field takes a list of "
								. "entry ids (e.g. [\"12\", \"15\"]). Fields you omit keep their current "
								. "values, but a field you supply is REPLACED wholesale — never supply a value you "
								. "only saw in truncated form (get_page lists those in content_truncated).",
							// A relationship field's value is a list of ids, so a string is
							// not the only shape this object carries (audit #11 B2).
							"additionalProperties" => [
								"anyOf" => [
									["type" => "string"],
									["type" => "array", "items" => ["type" => "string"]],
								],
							],
						],
						"template" => [
							"type" => "string",
							"description" => "Optional: switch the page to this template as part of the same edit. "
								. "Supply the new template's required fields in `content` — otherwise the switch "
								. "is refused rather than leaving the page broken.",
						],
						"save_as_draft" => [
							"type" => "boolean",
							"description" => "Queue this edit as a pending change instead of publishing it. Use when the user asks to "
								. "draft the change or have someone review it before it goes live — the live page is left "
								. "untouched. Without this a publisher's approval goes live immediately; an editor's always "
								. "queues either way.",
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["id"] ?? "")) === "") {

				return AIToolResult::error("A page id is required to edit a page's content.");
			}

			$validation = $this->backend->aiValidatePageContentUpdate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
