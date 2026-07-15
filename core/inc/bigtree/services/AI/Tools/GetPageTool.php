<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

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
							"type" => "integer",
							"description" => "Page id.",
						],
					],
					"required" => ["id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$id = (int)($args["id"] ?? 0);
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
