<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Read a page's SEO score and the admin's own recommendations for improving it.
	 *
	 * The assistant can already edit the fields the score is computed from — title,
	 * meta description, content — but had no way to answer "how's the SEO on this
	 * page?", so it either guessed from the raw fields or declined. This returns the
	 * admin's computation verbatim rather than re-deriving a second opinion. View
	 * access only, matching the admin's own rating panel; the backend re-checks.
	 */
	class GetPageSeoRatingTool extends AbstractReadTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_page_seo_rating";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Get a page's SEO rating (0-100) and the specific recommendations for improving it — the same "
					. "rating the admin shows. Use this to answer \"how's the SEO on this page?\" and to decide "
					. "what to change before proposing edits to its title, meta description or content.",
				[
					"type" => "object",
					"properties" => [
						"page_id" => [
							"type" => "integer",
							"description" => "Id of the page to rate.",
						],
					],
					"required" => ["page_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$page_id = (int)($args["page_id"] ?? 0);

			if ($page_id < 1) {

				return AIToolResult::error("A page_id is required.");
			}

			$result = $this->backend->aiPageSeoRating($page_id, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			return AIToolResult::ok($result);
		}
	}
