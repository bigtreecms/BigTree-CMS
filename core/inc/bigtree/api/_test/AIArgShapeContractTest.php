<?php
	/**
	 * Audit #7 Phase 1: the argument-shape contract at the model↔backend boundary.
	 *
	 * Every seam validates semantics meticulously and, until AIToolArgs, nothing
	 * validated that an argument arrived as the type its own definition() declares.
	 * The uniform response to a wrongly-shaped argument was `(array)$x` — silent
	 * coercion to empty, which for a replace-semantics argument (fields on
	 * update_template/update_callout) meant deleting every field.
	 *
	 * These are the E2/E3 guards from the plan. E2 pins the repair-vs-refuse decision
	 * (D1(a): repair one json_decode, else refuse) for every declared object/array
	 * property in the whole catalog, and proves the registry short-circuits before a
	 * tool's execute() ever runs. E3 pins the empty-replacement refusal.
	 */

	use BigTree\Services\AI\AIToolArgs;
	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolInterface;
	use BigTree\Services\AI\AIToolRegistry;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\TemplateService;
	use BigTree\Services\CalloutService;

	/**
	 * Every object/array property declared by any real tool, as
	 * [tool, argument, type]. Enumerated from the registry the chat turn actually
	 * builds, at developer level so the developer-only tools are included too.
	 *
	 * @return list<array{0:string,1:string,2:string}>
	 */
	function ai_argshape_composite_properties(): array {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$out = [];

		foreach ($registry->availableTools($developer) as $tool) {
			$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];

			if (!is_array($properties)) {

				continue;
			}

			foreach ($properties as $name => $spec) {
				$type = is_array($spec) ? ($spec["type"] ?? null) : null;

				if ($type === "object" || $type === "array") {
					$out[] = [$tool->name(), (string)$name, $type];
				}
			}
		}

		return $out;
	}

	/**
	 * E2: a composite argument supplied as plain (non-JSON) text is a recoverable
	 * error naming the argument — never a silent coercion to empty.
	 */
	function test_argshape_stringified_composite_is_refused() {
		$properties = ai_argshape_composite_properties();
		T::ok(count($properties) > 0, "the catalog has object/array arguments to guard");

		foreach ($properties as [$tool, $argument, $type]) {
			$definition = [
				"function" => [
					"parameters" => [
						"properties" => [$argument => ["type" => $type]],
					],
				],
			];
			$args = [$argument => "not json at all"];
			$error = AIToolArgs::validate($definition, $args);

			T::ok($error !== null, "{$tool}.{$argument}: a plain string is refused, not coerced");
			T::ok(
				strpos((string)$error, $argument) !== false,
				"{$tool}.{$argument}: the refusal names the argument so the model can fix it"
			);
			T::equals(
				$args[$argument],
				"not json at all",
				"{$tool}.{$argument}: the bad value is left untouched (no empty write)"
			);
		}
	}

	/**
	 * E2 (D1(a)): a composite argument supplied as a JSON *string* — the common
	 * provider quirk — is repaired in place with exactly one json_decode.
	 */
	function test_argshape_stringified_json_composite_is_repaired() {
		foreach ([["object", '{"page_header":"Hi"}', ["page_header" => "Hi"]], ["array", '["a","b"]', ["a", "b"]]] as $case) {
			[$type, $json, $expected] = $case;
			$definition = ["function" => ["parameters" => ["properties" => ["x" => ["type" => $type]]]]];
			$args = ["x" => $json];
			$error = AIToolArgs::validate($definition, $args);

			T::equals($error, null, "a stringified {$type} decodes cleanly");
			T::equals($args["x"], $expected, "and is repaired in place to the decoded value");
		}

		// A well-formed array is passed through untouched.
		$definition = ["function" => ["parameters" => ["properties" => ["x" => ["type" => "object"]]]]];
		$args = ["x" => ["a" => 1]];
		T::equals(AIToolArgs::validate($definition, $args), null, "a real object passes");
		T::equals($args["x"], ["a" => 1], "and is not disturbed");
	}

	/** E2: required arguments are enforced. */
	function test_argshape_required_is_enforced() {
		$definition = [
			"function" => ["parameters" => ["properties" => ["id" => ["type" => "string"]], "required" => ["id"]]],
		];
		$missing = [];
		T::ok(AIToolArgs::validate($definition, $missing) !== null, "a missing required argument is refused");

		$present = ["id" => "abc"];
		T::equals(AIToolArgs::validate($definition, $present), null, "a supplied required argument passes");

		$nulled = ["id" => null];
		T::ok(AIToolArgs::validate($definition, $nulled) !== null, "an explicit null is treated as missing");
	}

	/** E2: a scalar declared and an object/array sent is refused (the inverse of A1). */
	function test_argshape_object_where_string_declared_is_refused() {
		$definition = ["function" => ["parameters" => ["properties" => ["name" => ["type" => "string"]]]]];
		$args = ["name" => ["not" => "text"]];
		T::ok(AIToolArgs::validate($definition, $args) !== null, "an object where a string is declared is refused");
	}

	/**
	 * E2: and the whole point — the registry runs the contract before dispatch, so a
	 * wrongly-shaped argument never reaches a tool's execute(). Proven with a fake
	 * tool that records whether it ran.
	 */
	function test_argshape_registry_short_circuits_before_execute() {
		$registry = new AIToolRegistry();
		$tool = new ArgShapeSpyTool();
		$registry->register($tool);
		$context = new AIToolContext(ai_wiring_user(2));

		$result = $registry->execute("argshape_spy", ["fields" => "[{\"id\":\"x\"}]"], $context);

		// A stringified array is repairable, so it should reach execute repaired.
		T::ok($tool->ran, "a repairable stringified array reaches execute");
		T::equals($tool->seen, [["id" => "x"]], "and arrives decoded");

		$tool->ran = false;
		$result = $registry->execute("argshape_spy", ["fields" => "garbage"], $context);

		T::equals($result->type, AIToolResult::ERROR, "an unrepairable composite is a registry-level error");
		T::ok(!$tool->ran, "and the tool's execute() never runs");
	}

	/**
	 * E3: a genuinely empty replace-semantics field list is refused outright, rather
	 * than disclosed as an ordinary diff that empties the record.
	 */
	function test_argshape_empty_fields_list_is_refused() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$dev = ai_wiring_user(2);

		$templates = new TemplateService();
		$template_id = ai_argshape_existing("templates");

		if ($template_id !== "") {
			$result = $templates->aiValidateTemplateUpdate(["id" => $template_id, "fields" => []], $dev);
			T::ok(isset($result["error"]), "update_template refuses an empty field list");
			T::ok(
				stripos((string)($result["error"] ?? ""), "empty field list") !== false,
				"and says an empty list would remove every field"
			);
		}

		$callouts = new CalloutService();
		$callout_id = ai_argshape_existing("callouts");

		if ($callout_id !== "") {
			$result = $callouts->aiValidateCalloutUpdate(["id" => $callout_id, "fields" => []], $dev);
			T::ok(isset($result["error"]), "update_callout refuses an empty field list");
		}
	}

	/** First id in a JSON-DB store, or "" if none — used only as a validate fixture. */
	function ai_argshape_existing(string $store): string {
		$all = \BigTreeJSONDB::getAll($store);

		foreach ($all as $record) {
			if (isset($record["id"]) && $record["id"] !== "") {

				return (string)$record["id"];
			}
		}

		return "";
	}

	/** A minimal mutating tool that records the arguments its execute() receives. */
	class ArgShapeSpyTool implements AIToolInterface {
		public $ran = false;
		public $seen = null;

		public function name(): string {

			return "argshape_spy";
		}

		public function kind(): string {

			return "mutate";
		}

		public function isAvailable($user): bool {

			return true;
		}

		public function definition($user): array {

			return [
				"type" => "function",
				"function" => [
					"name" => "argshape_spy",
					"description" => "test tool",
					"parameters" => [
						"type" => "object",
						"properties" => ["fields" => ["type" => "array"]],
					],
				],
			];
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$this->ran = true;
			$this->seen = $args["fields"] ?? null;

			// A mutating tool must not return OK; a needs_input keeps the registry's
			// result-shape guard happy while proving execute() ran.
			return AIToolResult::needsInput("noop");
		}
	}
