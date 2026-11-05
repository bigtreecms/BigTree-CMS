<?php
	/**
	 * Every draw-time default a field type offers is applied by
	 * the server.
	 *
	 * `default_today`, `default_now` and `default_checked` were offered by
	 * field-type-schemas.php, with labels, and honoured only by legacy draw files
	 * that the remaster no longer renders. Nothing on the server read them, so every
	 * writer that never draws a form (the assistant above all) stored a blank where
	 * the field designer configured a value. A list's `allow-empty: No` was the same
	 * story one level down: the browser submitted the first option, the server never
	 * did.
	 *
	 * The sweep is discovered, not listed: any `bool` setting whose id starts with
	 * `default_`, plus `allow-empty`, must be read by
	 * FieldDefaultDomain::implicitDefault — or carry a reason here. A future
	 * `default_*` setting fails this test until somebody decides what it seeds.
	 */

	use BigTree\Services\AI\FieldDefaultDomain;
	use BigTree\Services\AI\FieldOptionDomain;

	/**
	 * Draw-time default settings deliberately not seeded server-side, with reasons.
	 *
	 * @return array<string,string> "type/setting" => reason
	 */
	function ai_implicit_default_exempt(): array {

		return [];
	}

	/**
	 * Every draw-time default setting the field-type registry declares, as
	 * "type/setting" => type.
	 *
	 * @return array<string,string>
	 */
	function ai_implicit_default_settings(): array {
		$schemas = (array)(require __DIR__ . "/../field-type-schemas.php");
		$found = [];

		foreach ($schemas as $type => $schema) {
			foreach ((array)($schema["settings_schema"] ?? []) as $descriptor) {
				$id = (string)($descriptor["id"] ?? "");
				$control = (string)($descriptor["control"] ?? "");

				if (($control === "bool" && strpos($id, "default_") === 0) || $id === "allow-empty") {
					$found["{$type}/{$id}"] = (string)$type;
				}
			}
		}

		return $found;
	}

	/** The source of a static method, read as text. */
	function ai_implicit_default_method_source(string $class, string $method): string {
		$reflection = new ReflectionMethod($class, $method);
		$lines = file((string)$reflection->getFileName());

		return implode("", array_slice(
			$lines,
			$reflection->getStartLine() - 1,
			$reflection->getEndLine() - $reflection->getStartLine() + 1
		));
	}

	function test_every_draw_time_default_is_seeded_by_the_server() {
		$found = ai_implicit_default_settings();
		$exempt = ai_implicit_default_exempt();
		$source = ai_implicit_default_method_source(FieldDefaultDomain::class, "implicitDefault");
		$unseeded = [];

		// Positive controls, so a registry reshuffle that hides them can't pass by
		// finding nothing.
		foreach (["date/default_today", "datetime/default_now", "checkbox/default_checked", "list/allow-empty"] as $key) {
			T::ok(isset($found[$key]), "the sweep discovers {$key}");
		}

		foreach ($found as $key => $type) {
			if (isset($exempt[$key])) {
				continue;
			}

			[, $setting] = explode("/", $key, 2);
			// allow-empty is read through FieldOptionDomain::allowsEmpty, which is the
			// one spelling of the rule shared with the settings path.
			$reads_setting = $setting === "allow-empty"
				? strpos($source, "FieldOptionDomain::allowsEmpty") !== false
				: strpos($source, '"' . $setting . '"') !== false;

			if (!$reads_setting || strpos($source, '"' . $type . '"') === false) {
				$unseeded[] = $key;
			}
		}

		T::equals(implode(", ", $unseeded), "", "every draw-time default is applied by FieldDefaultDomain::implicitDefault");
	}

	function test_implicit_default_exemptions_are_not_stale() {
		$found = ai_implicit_default_settings();

		foreach (ai_implicit_default_exempt() as $key => $reason) {
			T::ok(isset($found[$key]), "the {$key} exemption names a setting the registry still declares");
			T::ok(trim($reason) !== "", "the {$key} exemption states its reason");
		}
	}

	function test_the_spa_add_screens_seed_the_same_defaults() {
		$spa = (string)file_get_contents(dirname(__DIR__, 5) . "/spa/src/renderer/fields/drawDefaults.ts");
		$select = (string)file_get_contents(dirname(__DIR__, 5) . "/spa/src/renderer/fields/SelectField.tsx");
		$missing = [];

		foreach (array_keys(ai_implicit_default_settings()) as $key) {
			[, $setting] = explode("/", $key, 2);

			if (isset(ai_implicit_default_exempt()[$key])) {
				continue;
			}

			$haystack = $setting === "allow-empty" ? $select : $spa;

			if (strpos($haystack, $setting) === false) {
				$missing[] = $key;
			}
		}

		T::equals(implode(", ", $missing), "", "every draw-time default is seeded by the SPA's add screens too");
	}

	function test_implicit_default_values() {
		T::equals(
			FieldDefaultDomain::implicitDefault(["type" => "date", "settings" => ["default_today" => "on"]]),
			date("Y-m-d"),
			"default_today → today"
		);
		T::ok(
			preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)FieldDefaultDomain::implicitDefault(
				["type" => "datetime", "settings" => ["default_now" => "on"]]
			)) === 1,
			"default_now → a full timestamp"
		);
		T::equals(
			FieldDefaultDomain::implicitDefault(["type" => "checkbox", "settings" => ["default_checked" => "on"]]),
			"on",
			"default_checked → on"
		);
		T::equals(
			FieldDefaultDomain::implicitDefault([
				"type" => "checkbox",
				"settings" => ["default_checked" => "on", "custom_value" => "yes"],
			]),
			"yes",
			"…or the field's custom value"
		);
		T::equals(
			FieldDefaultDomain::implicitDefault([
				"type" => "list",
				"settings" => ["allow-empty" => "No"],
				"options" => [["value" => "news", "label" => "News"], ["value" => "events", "label" => "Events"]],
			]),
			"news",
			"a list with no blank option → its first option"
		);
		T::equals(
			FieldDefaultDomain::implicitDefault([
				"type" => "list",
				"settings" => ["allow-empty" => "Yes"],
				"options" => [["value" => "news", "label" => "News"]],
			]),
			null,
			"a list with a blank option has no implicit default"
		);
		T::equals(
			FieldDefaultDomain::implicitDefault(["type" => "list", "settings" => ["allow-empty" => "No"], "options" => []]),
			null,
			"…nor does one whose options can't be resolved"
		);
		T::equals(
			FieldDefaultDomain::implicitDefault(["type" => "date", "settings" => []]),
			null,
			"a field without the setting has none"
		);
		T::equals(
			FieldDefaultDomain::implicitDefault(["type" => "text", "settings" => ["default_today" => "on"]]),
			null,
			"the setting only means something on its own type"
		);
	}

	function test_implicit_defaults_skip_supplied_and_explicitly_defaulted_fields() {
		$fields = [
			["column" => "a", "title" => "A", "type" => "date", "settings" => ["default_today" => "on"]],
			["column" => "b", "title" => "B", "type" => "date", "settings" => ["default_today" => "on"]],
			["column" => "c", "title" => "C", "type" => "date", "settings" => ["default_today" => "on", "default" => "2030-01-01"]],
			["column" => "d", "title" => "D", "type" => "date", "settings" => ["default_today" => "on", "default" => ""]],
		];
		$applied = FieldDefaultDomain::implicitDefaults($fields, ["b" => ""], "column");

		T::ok(isset($applied["a"]), "an omitted field is seeded");
		T::ok(!isset($applied["b"]), "a supplied value — even \"\" — is never touched");
		T::ok(!isset($applied["c"]), "an explicit default wins");
		T::ok(isset($applied["d"]), "an empty explicit default is no default");
		T::equals(
			FieldDefaultDomain::describeApplied(["a" => ["title" => "A", "value" => "x"], "d" => ["title" => "D", "value" => "y"]]),
			"A: x; D: y",
			"the card row names each field and its value"
		);
	}

	function test_allow_empty_no_refuses_an_explicit_blank() {
		$options = [["value" => "news", "label" => "News"], ["value" => "events", "label" => "Events"]];
		$value = "";

		T::ok(
			FieldOptionDomain::violation(["title" => "Category", "options" => $options, "settings" => ["allow-empty" => "No"]], $value) !== null,
			"a blank is refused when the list has no blank option"
		);

		$value = "";
		T::equals(
			FieldOptionDomain::violation(["title" => "Category", "options" => $options, "settings" => []], $value),
			null,
			"…and allowed when it does (the default)"
		);

		$value = "";
		T::equals(
			FieldOptionDomain::violation(["title" => "Category", "options" => [], "settings" => ["allow-empty" => "No"]], $value),
			null,
			"an unresolvable list refuses nothing"
		);
	}
