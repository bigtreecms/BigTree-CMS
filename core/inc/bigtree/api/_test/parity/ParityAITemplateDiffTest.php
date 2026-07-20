<?php
	/**
	 * A7: update_template's proposal preview.
	 *
	 * aiValidateTemplateUpdate replaces a template's whole resources list but showed
	 * only a field *count*, so an approver couldn't see that fields were dropped
	 * (orphaning their content on every page using the template) or that a required
	 * rule was lost. These drive the real diff builder.
	 *
	 * Needs the DB only for the "pages using this template" count.
	 */

	use BigTree\Services\TemplateService;

	/** Call the private diff builder directly. */
	function parity_template_field_diff(array $before, array $after, string $template_id): array {
		$ref = new ReflectionMethod(TemplateService::class, "aiTemplateFieldDiff");
		$ref->setAccessible(true);

		return $ref->invokeArgs(new TemplateService(), [$before, $after, $template_id]);
	}

	function parity_template_resource(string $id, string $type = "text", bool $required = false): array {

		return [
			"id" => $id,
			"type" => $type,
			"title" => ucfirst($id),
			"subtitle" => "",
			"settings" => $required ? ["validation" => "required"] : [],
		];
	}

	function test_parity_ai_template_diff_names_added_and_removed_fields() {
		if (!parity_db_available()) {
			return;
		}

		$before = [parity_template_resource("body"), parity_template_resource("sidebar")];
		$after = [parity_template_resource("body"), parity_template_resource("footer")];

		$diff = parity_template_field_diff($before, $after, "content");

		T::ok(isset($diff["fields_added"]), "added fields are reported");
		T::ok(strpos((string)$diff["fields_added"], "footer") !== false, "the added field is named");
		T::ok(isset($diff["fields_removed"]), "removed fields are reported");
		T::ok(strpos((string)$diff["fields_removed"], "sidebar") !== false, "the removed field is named");
		T::ok(array_key_exists("pages_using_template", $diff), "the affected page count is included");
	}

	function test_parity_ai_template_diff_flags_retyped_and_unrequired_fields() {
		if (!parity_db_available()) {
			return;
		}

		$before = [
			parity_template_resource("body", "html", true),
			parity_template_resource("blurb", "text"),
		];

		// The assistant's field input carries no settings, so "body" keeps its name
		// and type but silently loses its required rule; "blurb" changes type.
		$after = [
			parity_template_resource("body", "html"),
			parity_template_resource("blurb", "textarea"),
		];

		$diff = parity_template_field_diff($before, $after, "content");

		T::ok(isset($diff["no_longer_required"]), "a lost required rule is surfaced");
		T::ok(strpos((string)$diff["no_longer_required"], "body") !== false, "the de-required field is named");
		T::ok(isset($diff["fields_retyped"]), "a type change is surfaced");
		T::ok(strpos((string)$diff["fields_retyped"], "text → textarea") !== false, "the type change shows both types");
	}

	function test_parity_ai_template_diff_is_quiet_when_nothing_structural_changed() {
		if (!parity_db_available()) {
			return;
		}

		$fields = [parity_template_resource("body", "html"), parity_template_resource("blurb")];
		$diff = parity_template_field_diff($fields, $fields, "content");

		foreach (["fields_added", "fields_removed", "fields_retyped", "no_longer_required"] as $key) {
			T::ok(!isset($diff[$key]), "{$key} is omitted when nothing changed");
		}
	}
