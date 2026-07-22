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
							"type" => "string",
							"description" => "Entry row id in the module table. Use a \"p\"-prefixed id (like "
								. "\"p12\") to read an entry that is still an unpublished draft.",
						],
						"form" => [
							"type" => "string",
							"description" => "Optional form id. Required on a module with more than one entry form, "
								. "since each form has its own table — the same argument the entry write tools take.",
						],
					],
					"required" => ["module_id", "entry_id"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$module_id = (string)($args["module_id"] ?? "");
			// Not cast to int: "p12" addresses a draft, and the backend resolves both.
			$entry_id = trim((string)($args["entry_id"] ?? ""));
			$detail = $this->backend->getModuleEntryDetail(
				$module_id,
				$entry_id,
				$context->user,
				trim((string)($args["form"] ?? ""))
			);

			if (isset($detail["error"])) {
				return AIToolResult::error((string)$detail["error"]);
			}

			// A multi-form module has no safe default table — the same choice the write
			// tools hand back rather than reading the wrong form's row.
			if (!empty($detail["ambiguous_form"])) {
				return AIToolResult::needsInput(
					"Which form should I read this entry from?",
					array_map(function (array $form): array {

						return [
							"id" => (string)$form["id"],
							"label" => (string)($form["title"] !== "" ? $form["title"] : $form["id"]),
							"description" => "Table: " . (string)$form["table"],
						];
					}, is_array($detail["forms"] ?? null) ? $detail["forms"] : [])
				);
			}

			$artifact = $detail["artifact"] ?? null;

			return AIToolResult::ok(
				$detail["payload"] ?? [],
				$artifact ? ["entries" => [$artifact]] : []
			);
		}
	}
