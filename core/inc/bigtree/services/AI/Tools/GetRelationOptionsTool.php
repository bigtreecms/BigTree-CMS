<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;

	/**
	 * The rows a relationship field can be filed against.
	 *
	 * Audit #12 B1. Audit #11 B2 made `one-to-many` and `many-to-many` writable by
	 * entry id — carefully: RelationDomain builds the descriptor from the field
	 * definition, checks every id against the target table, applies per-row
	 * permissions and honours the field's `max`. What shipped without it was any way
	 * to *find* the ids. A relation's target table is named by the field's own
	 * settings and is usually a plain lookup table (`categories`, `regions`,
	 * `staff_types`) that no module owns, so list_module_entries — which takes a
	 * module id and resolves a table through its form — cannot reach it, and nothing
	 * maps a table back to a module for the model to work out which module to ask
	 * for. A capability granted without its lookup is one that works by luck.
	 *
	 * The endpoint behind this is the one the admin's own relation picker uses, so
	 * the rows offered here are the rows a person would be offered.
	 */
	class GetRelationOptionsTool extends AbstractReadTool {
		/** @var ModuleEntryToolBackend */
		private $backend;

		public function __construct(ModuleEntryToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "get_relation_options";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List the entries a relationship field (one-to-many or many-to-many) can be linked to, as "
					. "id/title pairs. Use this before writing such a field with create_module_entry or "
					. "update_module_entry — those take row ids, and the rows live in the field's own target "
					. "table, which is usually not a module you can list.",
				[
					"type" => "object",
					"properties" => [
						"module_id" => [
							"type" => "string",
							"description" => "Module id of the module whose form has the relationship field.",
						],
						"column" => [
							"type" => "string",
							"description" => "The relationship field's column, as get_module_schema reports it.",
						],
						"form" => [
							"type" => "string",
							"description" => "Optional form id. Required on a module with more than one entry form.",
						],
						"query" => [
							"type" => "string",
							"description" => "Optional text to match against the row labels — use it rather than "
								. "paging when the list is long.",
						],
						"limit" => [
							"type" => "integer",
							"description" => "How many rows to return (1–50, default 25).",
						],
						"offset" => [
							"type" => "integer",
							"description" => "How many rows to skip, for reading the next page when has_more is "
								. "true. Narrowing with `query` is usually better than paging.",
						],
					],
					"required" => ["module_id", "column"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$module_id = trim((string)($args["module_id"] ?? ""));
			$column = trim((string)($args["column"] ?? ""));

			if ($module_id === "") {

				return AIToolResult::error("A module_id is required.");
			}

			if ($column === "") {

				return AIToolResult::error("A column is required — get_module_schema lists them.");
			}

			$result = $this->backend->aiRelationOptions(
				$module_id,
				trim((string)($args["form"] ?? "")),
				$column,
				trim((string)($args["query"] ?? "")),
				(int)($args["limit"] ?? 25),
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
			// other entry tool hands back rather than reading the wrong form's fields.
			if (!empty($result["ambiguous_form"])) {

				return AIToolResult::needsInput(
					"Which form's field should I look up?",
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
