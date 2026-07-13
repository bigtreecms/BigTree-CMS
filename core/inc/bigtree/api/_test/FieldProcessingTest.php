<?php
	/**
	 * FieldProcessingService::processField characterization.
	 * Exercises core text field, no-handler fallback, and validation errors.
	 * Skips when the DB is unavailable (processField bridges a legacy admin
	 * whose constructor reads settings).
	 */

	use BigTree\Services\FieldProcessingService;

	function _field_processing_skip_if_no_db() {
		try {
			SQL::fetchSingle("SELECT 1");
		} catch (Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return true;
		}

		return false;
	}

	function test_field_processing_text_passthrough() {
		if (_field_processing_skip_if_no_db()) {
			return;
		}

		global $bigtree;

		$bigtree["errors"] = [];
		$field = [
			"type" => "text",
			"title" => "Title",
			"input" => "Hello <b>world</b>",
			"settings" => [],
		];

		$out = FieldProcessingService::processField($field);
		T::ok(is_string($out), "text field returns a string");
		T::ok(strpos($out, "Hello") !== false, "output preserves content");
	}

	function test_field_processing_unknown_type_safe_encode() {
		if (_field_processing_skip_if_no_db()) {
			return;
		}

		global $bigtree;

		$bigtree["errors"] = [];
		$field = [
			"type" => "totally-unknown-field-type-xyz",
			"title" => "Custom",
			"input" => '<script>alert(1)</script>',
			"settings" => [],
		];

		$out = FieldProcessingService::processField($field);
		T::ok(is_string($out), "unknown type falls back to safeEncode string");
		T::ok(strpos($out, "<script>") === false, "script tags are encoded away");
	}

	function test_field_processing_validation_failure() {
		if (_field_processing_skip_if_no_db()) {
			return;
		}

		global $bigtree;

		$bigtree["errors"] = [];
		$field = [
			"type" => "text",
			"title" => "Required Field",
			"input" => "",
			"settings" => [
				"validation" => "required",
			],
		];

		FieldProcessingService::processField($field);
		T::ok(!empty($bigtree["errors"]), "validation failure populates bigtree errors");
		T::equals($bigtree["errors"][0]["field"] ?? null, "Required Field", "error names the field");
	}

	function test_field_processing_facade_matches_service() {
		if (_field_processing_skip_if_no_db()) {
			return;
		}

		global $bigtree;

		// Touching BigTreeAdmin loads the facade; both paths must agree.
		$bigtree["errors"] = [];
		$field = [
			"type" => "text",
			"title" => "Parity",
			"input" => "same value",
			"settings" => [],
		];

		$via_service = FieldProcessingService::processField($field);
		$bigtree["errors"] = [];
		$via_facade = \BigTreeAdmin::processField($field);
		T::equals($via_service, $via_facade, "facade and service processField return identical output");
	}
