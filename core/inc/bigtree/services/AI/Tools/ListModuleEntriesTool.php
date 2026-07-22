<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * List one module's entries, newest first.
	 *
	 * search_module_entries requires a query, sweeps every accessible module, and
	 * slices to five rows per module — so "list the ten most recent news posts" had
	 * no answer, and the model had no way to know it had only been shown five.
	 * `GET /modules/{id}/entries` has had this all along; this is its tool half.
	 */
	class ListModuleEntriesTool extends AbstractReadTool {
		/** @var ModuleEntryToolBackend */
		private $backend;

		public function __construct(ModuleEntryToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "list_module_entries";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List a module's entries, newest first, with the fields the assistant can read and write. Use "
					. "this for \"show me the latest X\" — search_module_entries is for finding a specific one by "
					. "keyword. The result says whether more entries exist beyond the window you asked for.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Module id (string, e.g. modules-…). Use search_modules to find it.",
						],
						"form" => [
							"type" => "string",
							"description" => "Optional form id. Required on a module with more than one entry form, "
								. "since each form has its own table.",
						],
						"limit" => [
							"type" => "integer",
							"description" => "How many entries to return (1–50, default 10).",
						],
						"offset" => [
							"type" => "integer",
							"description" => "How many entries to skip, for paging past the first window.",
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

			$result = $this->backend->aiListEntries(
				$module_id,
				trim((string)($args["form"] ?? "")),
				(int)($args["limit"] ?? 10),
				(int)($args["offset"] ?? 0),
				$context->user
			);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			// A multi-form module has no safe default table — the same choice every
			// other entry tool hands back rather than reading the wrong form's rows.
			if (!empty($result["ambiguous_form"])) {

				return AIToolResult::needsInput(
					"Which form's entries should I list?",
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
