<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * Read a module entry form's full field list — every field with its type, whether
	 * it's required, and whether the assistant can actually set it.
	 *
	 * Before this the settable-field list only ever appeared inside an error message,
	 * so discovering a module's shape cost a deliberately-failed write, and fields
	 * the assistant could never fill were invisible until a create was refused. With
	 * this the model can tell the user up front what it can't fill in.
	 */
	class GetModuleSchemaTool extends AbstractReadTool {
		/** @var ModuleEntryToolBackend */
		private $backend;

		public function __construct(ModuleEntryToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_module_schema";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Fetch the field list for a module's entry form: each field's column, type, whether it is required, "
					. "and whether the assistant can set it. Call this before creating or updating a module entry "
					. "so you know which fields exist and which ones a person will have to fill in themselves.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Module id or route (required).",
						],
						"form" => [
							"type" => "string",
							"description" => "Optional form id, for a module that has more than one entry form.",
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

			$result = $this->backend->aiModuleSchema($module_id, trim((string)($args["form"] ?? "")), $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			// More than one entry form and none named — ask rather than guess, the same
			// way the entry-mutating tools do.
			if (!empty($result["ambiguous_form"])) {

				return AIToolResult::needsInput(
					"Which form's schema do you want?",
					array_map(function (array $form): array {

						return [
							"id" => (string)$form["id"],
							"label" => (string)($form["title"] !== "" ? $form["title"] : $form["id"]),
							"description" => "Table: " . (string)$form["table"],
						];
					}, is_array($result["forms"] ?? null) ? $result["forms"] : [])
				);
			}

			return AIToolResult::ok($result);
		}
	}
