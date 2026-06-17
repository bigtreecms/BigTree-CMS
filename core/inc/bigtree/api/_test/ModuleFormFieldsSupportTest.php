<?php
	/**
	 * ModuleFormFieldsSupport (plan 029, phase A) holds cleanFormFields, lifted
	 * out of ModuleService so both ModuleService (scaffold + embed-form CRUD) and
	 * the extracted ModuleFormService (form CRUD) can share it. cleanFormFields is
	 * pure (no $this-> deps, no DB/store), so these tests exercise it directly
	 * through a throwaway host class that `use`s the trait — no seeding required.
	 *
	 * Coverage map:
	 *   - canonical shape       — column/type/title/subtitle/settings normalized,
	 *                             type defaults to "text"
	 *   - column from array key — when a field omits "column", the (string) key is
	 *                             used; numeric keys with no column are dropped
	 *   - rejects junk          — non-array entries and column-less entries skipped
	 *   - settings coercion     — JSON-string settings decoded; "options" alias
	 *                             honored; empties filtered out recursively
	 *   - non-array input       — returns []
	 */

	use BigTree\Services\ModuleFormFieldsSupport;

	/** Throwaway host that exposes the trait's private cleanFormFields. */
	class _FormFieldsHost {
		use ModuleFormFieldsSupport;

		public function clean($fields) {

			return $this->cleanFormFields($fields);
		}
	}

	function _ff_clean($fields) {
		static $host = null;

		if ($host === null) {
			$host = new _FormFieldsHost();
		}

		return $host->clean($fields);
	}

	function test_formfields_non_array_returns_empty() {
		T::equals(_ff_clean("nope"), [], "cleanFormFields returns [] for a non-array input");
		T::equals(_ff_clean(null), [], "cleanFormFields returns [] for null input");
	}

	function test_formfields_canonical_shape_and_type_default() {
		$out = _ff_clean([
			["column" => "headline", "title" => "Headline", "subtitle" => "Sub"],
		]);

		T::equals(count($out), 1, "one valid field yields one cleaned field");
		T::equals($out[0]["column"], "headline", "column is preserved");
		T::equals($out[0]["type"], "text", "type defaults to text when omitted");
		T::equals($out[0]["title"], "Headline", "title is preserved");
		T::equals($out[0]["subtitle"], "Sub", "subtitle is preserved");
		T::equals($out[0]["settings"], [], "empty settings normalize to []");
	}

	function test_formfields_column_falls_back_to_string_key() {
		$out = _ff_clean([
			"body" => ["type" => "html", "title" => "Body"],
		]);

		T::equals(count($out), 1, "field keyed by a string column is kept");
		T::equals($out[0]["column"], "body", "column is taken from the string array key");
		T::equals($out[0]["type"], "html", "supplied type is preserved");
	}

	function test_formfields_drops_junk_and_columnless_entries() {
		$out = _ff_clean([
			"valid_col" => ["type" => "text"],
			"skip_me" => "not-an-array",
			["type" => "text", "title" => "no column, numeric key"],
		]);

		T::equals(count($out), 1, "non-array and column-less (numeric-key) entries are dropped");
		T::equals($out[0]["column"], "valid_col", "only the valid field survives");
	}

	function test_formfields_settings_json_string_and_options_alias() {
		$out = _ff_clean([
			["column" => "a", "settings" => '{"min":"1","blank":""}'],
			["column" => "b", "options" => ["max" => "9", "empty" => ""]],
		]);

		T::equals($out[0]["settings"], ["min" => "1"], "JSON-string settings are decoded and empties filtered");
		T::equals($out[1]["settings"], ["max" => "9"], "the options alias is honored and empties filtered");
	}
