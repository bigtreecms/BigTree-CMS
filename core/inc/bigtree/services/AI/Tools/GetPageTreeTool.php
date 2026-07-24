<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Navigate the page tree: the children of a given parent (0 = site root) plus
	 * whether the user may create or edit under it. Lets the model orient itself
	 * ("what lives under Blog?", "where can I add a page?") without guessing ids.
	 *
	 * Read-only and offered to everyone; the backend omits any child the user cannot
	 * view, so the model never sees a page it has no access to.
	 */
	class GetPageTreeTool extends AbstractReadTool {
		/** @var PageToolBackend */
		private $backend;

		public function __construct(PageToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_page_tree";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List the child pages under a parent page (use parent 0 for the site root), and whether you "
					. "can create or edit pages there. Use this to explore the site structure or find where a page can go. "
					. "A large section is returned in pages: if has_more is true, call again with offset advanced by the "
					. "number of children returned.",
				[
					"type" => "object",
					"properties" => [
						"parent" => [
							"type" => "integer",
							"description" => "Id of the parent page to list children of. 0 (default) is the site root.",
						],
						"offset" => [
							"type" => "integer",
							"description" => "How many children to skip, for walking a section with more children than fit "
								. "in one response. 0 (the default) starts at the beginning.",
						],
					],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$parent = (int)($args["parent"] ?? 0);
			$offset = max(0, (int)($args["offset"] ?? 0));
			$tree = $this->backend->aiPageTree($parent, $context->user, $offset);

			if (isset($tree["error"])) {

				return AIToolResult::error((string)$tree["error"]);
			}

			$artifacts = [];

			foreach ($tree["children"] as $child) {
				$artifacts[] = [
					"id" => $child["id"],
					"nav_title" => $child["nav_title"],
					"path" => $child["path"],
				];
			}

			return AIToolResult::ok($tree, $artifacts ? ["pages" => $artifacts] : []);
		}
	}
