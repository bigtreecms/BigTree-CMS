<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	class GetModuleEntryTool extends AbstractSearchTool {
		public function name(): string {

			return "get_module_entry";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Fetch a single module entry by module id and entry id.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Module id (string, e.g. modules-…).",
						],
						"entry_id" => [
							"type" => "integer",
							"description" => "Entry row id in the module table.",
						],
					],
					"required" => ["module_id", "entry_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$module_id = (string)($args["module_id"] ?? "");
			$entry_id = (int)($args["entry_id"] ?? 0);
			$detail = $this->backend->getModuleEntryDetail($module_id, $entry_id, $context->user);

			if (isset($detail["error"])) {
				return AIToolResult::error((string)$detail["error"]);
			}

			$artifact = $detail["artifact"] ?? null;

			return AIToolResult::ok(
				$detail["payload"] ?? [],
				$artifact ? ["entries" => [$artifact]] : []
			);
		}
	}
