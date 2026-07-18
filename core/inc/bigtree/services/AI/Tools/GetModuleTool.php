<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Fetch one module's definition — its table, forms, views, and what setup it is
	 * still missing.
	 *
	 * Pairs with create_module: a bare module record appears in the admin navigation
	 * but has no table, form, view or action, and the assistant previously had no way
	 * to see that. Access is checked against the caller's module level.
	 */
	class GetModuleTool extends AbstractReadTool {
		/** @var ModuleToolBackend */
		private $backend;

		public function __construct(ModuleToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_module";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Fetch a single module's definition by id or route: its table, entry forms, views, and a list of "
					. "any setup it still needs before it can hold entries. Use this to check whether a module is "
					. "usable before trying to add entries to it.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Module id or route.",
						],
					],
					"required" => ["module_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$module_id = trim((string)($args["module_id"] ?? ""));

			if ($module_id === "") {

				return AIToolResult::error("A module_id is required.");
			}

			$result = $this->backend->aiGetModule($module_id, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			return AIToolResult::ok($result);
		}
	}
