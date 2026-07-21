<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Read one page — live, or an unpublished draft addressed as "p{id}".
	 *
	 * Draft addressing mirrors update_page/update_page_content: the assistant's own
	 * editor-level create_page lands in the pending queue, so without it the model
	 * could edit a draft it had just created but never read it back.
	 */
	class GetPageTool extends AbstractSearchTool {
		public function name(): string {

			return "get_page";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Fetch a single page's metadata and plain-text content snippet.",
				[
					"type" => "object",
					"properties" => [
						"id" => [
							"type" => "string",
							"description" => "Page id. Use the numeric id of a live page, or a \"p\"-prefixed id "
								. "(like \"p12\") to read a page that is still an unpublished draft — "
								. "get_pending_changes lists those.",
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$id = trim((string)($args["id"] ?? ""));

			if ($id === "") {

				return AIToolResult::error("A page id is required.");
			}

			$detail = $this->backend->getPageDetail($id, $context->user);

			if (isset($detail["error"])) {
				return AIToolResult::error((string)$detail["error"]);
			}

			$artifact = $detail["artifact"] ?? null;

			return AIToolResult::ok(
				$detail["payload"] ?? [],
				$artifact ? ["pages" => [$artifact]] : []
			);
		}
	}
