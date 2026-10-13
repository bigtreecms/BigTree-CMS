<?php
	/**
	 * Audit #30: the one field type whose value comes from neither the model nor the
	 * person, but from a third-party service at write time.
	 *
	 * A `geocoding` field has no value of its own: at save it concatenates its source
	 * columns, calls a geocoder, and writes `latitude` / `longitude` onto the row.
	 * The remaster's shared seam mirrored the legacy processor's *success* path only,
	 * and legacy did three things on failure it dropped — it cleared both
	 * coordinates, it surfaced the geocoder's own error to the editor, and (by
	 * clearing) it never let a row carry coordinates belonging to a previous address.
	 *
	 * That was structurally invisible for the same reason audit #29's finding was:
	 * every value-domain and required-field guard keys its rule to a value the model
	 * supplies, and AIRequiredFieldGateTest asserts — correctly — that `route` and
	 * `geocoding` are not gaps because "they are populated by applyEntryProcessors at
	 * write time". Nothing then asked what *populated* means when the lookup fails.
	 *
	 * And the failure case is the common case, not an edge: BigTreeGeocoding defaults
	 * to Google with whatever `google_key` is stored, so on an install where
	 * Developer → System → Geocoding was never filled in — the state every fresh
	 * install is in — every lookup in every geocoding module fails.
	 *
	 * This file holds the fixtures the other audit #30 guards share, plus E3
	 * (get_module_schema names the derived columns) and E6 (get_module names a
	 * geocoding form whose table has no coordinate columns). E1's write legs live in
	 * parity/ParityAIEntryProcessorsTest, beside the route legs they belong with.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\AI\ColumnDomain;

	/**
	 * A geocoder that answers whatever the test says, without a socket.
	 *
	 * BigTree::cURL has no stub hook, so pre-seeding the geocode cache can only
	 * cover the success leg — and the failure leg is the one this audit is about.
	 * AutoModuleService::$geocoder_factory exists for exactly this (audit #30 D5);
	 * it is the whole of the production change made for testability.
	 */
	class AIGeocodingStub {
		/** @var string|false the geocoder's own error text, as BigTreeGeocoding sets it */
		public $Error = false;

		/** @var list<string> every address this stub was asked about */
		public $addresses = [];

		/** @var array{latitude:mixed,longitude:mixed}|false what geocode() answers */
		public $result = false;

		public function __construct($result, $error = false) {
			$this->result = $result;
			$this->Error = $error;
		}

		public function geocode($address, $ignore_cache = false) {
			$this->addresses[] = (string)$address;

			return $this->result;
		}
	}

	/**
	 * Install a stub geocoder for the rest of this test, and hand it back so the
	 * test can read what it was asked. Every caller resets in its own `finally`.
	 *
	 * @param array{latitude:mixed,longitude:mixed}|false $result
	 * @param string|false $error
	 */
	function ai_geocoding_stub($result, $error = false): AIGeocodingStub {
		$stub = new AIGeocodingStub($result, $error);

		AutoModuleService::$geocoder_factory = function () use ($stub) {

			return $stub;
		};

		return $stub;
	}

	/** Put the real geocoder back. Called from every fixture teardown. */
	function ai_geocoding_stub_reset(): void {
		AutoModuleService::$geocoder_factory = null;
	}

	/**
	 * A throwaway module whose form geocodes, over a throwaway table.
	 *
	 * Built the way ai_feature_switch_module builds one (audit #27), with the two
	 * address columns the field is sourced from and — unless the caller asks for the
	 * A3 shape — the two coordinate columns it writes into.
	 *
	 * @param array<string,mixed> $options `no_coordinate_columns`
	 * @return array{0:string,1:string,2:string} [module id, form id, table]
	 */
	function ai_geocoding_module(string $suffix, array $options = []): array {
		$table = "zz_audit30_{$suffix}";
		$coordinates = empty($options["no_coordinate_columns"])
			? ", `latitude` VARCHAR(191) NOT NULL DEFAULT '', `longitude` VARCHAR(191) NOT NULL DEFAULT ''"
			: "";

		SQL::query("CREATE TABLE `{$table}` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, "
			. "`title` VARCHAR(191) NOT NULL DEFAULT '', `street` VARCHAR(191) NOT NULL DEFAULT '', "
			. "`city` VARCHAR(191) NOT NULL DEFAULT ''{$coordinates}, PRIMARY KEY (`id`)) ENGINE=InnoDB "
			. "DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
		ColumnDomain::forget($table);

		$module_id = BigTreeJSONDB::insert("modules", [
			"name" => "ZZ Audit30 {$suffix}",
			"route" => "zz-audit30-{$suffix}",
			// Read unguarded when the module class list is rebuilt.
			"class" => "",
			// Legacy auto-modules reads both of these unguarded while recaching a view,
			// and it walks every module's views — a fixture missing them warns from
			// other tests' writes too.
			"gbp" => ["name" => "", "table" => "", "item_parser" => "", "other_table" => ""],
			"forms" => [[
				"id" => "form-{$suffix}",
				"title" => "Location",
				"table" => $table,
				"fields" => [
					["id" => "title", "title" => "Title", "type" => "text", "column" => "title", "settings" => []],
					["id" => "street", "title" => "Street", "type" => "text", "column" => "street", "settings" => []],
					["id" => "city", "title" => "City", "type" => "text", "column" => "city", "settings" => []],
					// The field's own column is not where its value lands — it has no
					// value. `fields` names the columns the address is built from.
					["id" => "geo", "title" => "Map", "type" => "geocoding", "column" => "geo",
						"settings" => ["fields" => ["street", "city"]]],
				],
			]],
			"views" => [[
				"id" => "view-{$suffix}",
				"title" => "Locations",
				"type" => "searchable",
				"table" => $table,
				"fields" => ["title" => ["width" => "", "title" => "Title", "parser" => "", "numeric" => false]],
				"settings" => ["filter" => "", "sort" => "`id` DESC", "per_page" => "15"],
				"actions" => ["edit" => "on"],
			]],
			"actions" => [[
				"id" => "action-{$suffix}",
				"name" => "View Locations",
				"route" => "view-locations",
				"in_nav" => "on",
				"level" => 0,
				"view" => "view-{$suffix}",
			]],
		]);

		return [(string)$module_id, "form-{$suffix}", $table];
	}

	/** Drop a fixture module and its table. Best-effort: a fixture must not fail a run. */
	function ai_geocoding_teardown(string $suffix, string $table): void {
		ai_geocoding_stub_reset();
		// By route rather than by id: a store that refused the write still returns
		// false from insert, and the route is this test's own either way.
		BigTreeJSONDB::delete("modules", "zz-audit30-{$suffix}", "route");

		try {
			SQL::query("DROP TABLE IF EXISTS `{$table}`");
			SQL::query("DELETE FROM bigtree_module_view_cache WHERE view = ?", "view-{$suffix}");
		} catch (Throwable $e) {
			// disposable
		}

		ColumnDomain::forget($table);
	}

	/** Whether this run can build a fixture module at all. */
	function ai_geocoding_ready(): bool {

		return function_exists("parity_db_available") && parity_db_available();
	}

	/**
	 * E3: the read seam names the columns the write fills.
	 *
	 * `route` and `geocoding` are refused as settable types and are then, quite
	 * correctly, absent from every list get_module_schema carries — including
	 * aiOptionalUnsettableFields, which skips derived types on purpose. So nothing
	 * named `latitude` / `longitude` as columns the write would fill, nor which
	 * source columns feed them: the model could not tell a person why the map pin
	 * moved or didn't, and had no reason to include every part of an address on a
	 * create (audit #30 A2).
	 *
	 * Fails on the tree before this pass: the schema has no `derived_columns` key.
	 */
	function test_get_module_schema_names_the_columns_the_write_derives() {
		if (!ai_geocoding_ready()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "";

		try {
			[$module_id, $form_id, $table] = ai_geocoding_module($suffix);
			$schema = (new AutoModuleService())->aiModuleSchema($module_id, $form_id, ai_wiring_user(2));
			$derived = $schema["schema"]["derived_columns"] ?? null;

			T::ok(is_array($derived) && count($derived) === 1, "the schema carries one derived-column entry");

			$entry = is_array($derived) ? ($derived[0] ?? []) : [];

			T::equals((string)($entry["kind"] ?? ""), "geocoding", "…and says what kind of derivation it is");
			T::equals($entry["columns"] ?? null, ["latitude", "longitude"], "…the columns it writes");
			T::equals($entry["source_columns"] ?? null, ["street", "city"], "…and the columns it reads");
			T::ok(
				(string)($schema["schema"]["derived_columns_note"] ?? "") !== "",
				"…with a note saying these are filled by the write rather than by the model"
			);

			// The half a card can't carry, because it depends on a developer setting:
			// whether the lookup will actually answer. Named either way, and never
			// with a key — D3 reads the internal setting the way BigTreeGeocoding
			// does, and the route family that would hand back the credentials is
			// declined to the assistant precisely for that reason.
			$note = (string)($entry["note"] ?? "");

			T::ok($note !== "", "the geocoding entry carries its own note");
			T::ok(
				stripos($note, "geocoding service") !== false,
				"…which says whether a geocoding service is configured ({$note})"
			);

			foreach (["google_key", "bing_key", "mapquest_key"] as $key) {
				T::ok(strpos($note, $key) === false, "…and never names the {$key} setting, let alone its value");
			}

			// The rule this must not break: a derived field is still not a gap.
			$settable = [];

			foreach ((array)($schema["schema"]["fields"] ?? []) as $field) {
				if (!empty($field["assistant_can_set"])) {
					$settable[] = (string)$field["column"];
				}
			}

			T::equals($settable, ["title", "street", "city"], "the derived field is still not offered as settable");
			T::equals(
				$schema["schema"]["blocked_required"] ?? null,
				[],
				"…and is not reported as a required field the assistant failed to supply"
			);
		} finally {
			ai_geocoding_teardown($suffix, $table);
		}
	}

	/**
	 * E6 (A3): a geocoding form over a table with no coordinate columns.
	 *
	 * `applyGeocoding` writes `latitude` / `longitude` unconditionally and
	 * `BigTreeAutoModule::createItem` drops any key `describeTable` doesn't list, so
	 * a legacy-built module in this shape geocodes on every save, stores nothing and
	 * reports success. Legacy did the same, so nothing here creates a column — but
	 * `get_module`'s missing_setup is exactly the read whose job is to say what a
	 * module still needs, and it said nothing.
	 */
	function test_get_module_reports_a_geocoding_form_with_no_coordinate_columns() {
		if (!ai_geocoding_ready()) {

			return;
		}

		$without = substr(md5(uniqid("", true)), 0, 8);
		$with = substr(md5(uniqid("", true)), 0, 8);
		$without_table = "";
		$with_table = "";

		try {
			[$without_id, , $without_table] = ai_geocoding_module($without, ["no_coordinate_columns" => true]);
			$modules = new \BigTree\Services\ModuleService();
			$read = $modules->aiGetModule($without_id, ai_wiring_user(2));
			$missing = (array)($read["module"]["missing_setup"] ?? []);
			$gap = "";

			foreach ($missing as $line) {
				if (stripos((string)$line, "latitude") !== false) {
					$gap = (string)$line;
				}
			}

			T::ok($gap !== "", "missing_setup names the absent coordinate columns (" . implode(" | ", $missing) . ")");
			T::ok(stripos($gap, "longitude") !== false, "…both of them");
			T::ok(stripos($gap, "geocoding") !== false, "…and says which field needs them");
			T::ok(strpos($gap, $without_table) !== false, "…on which table");
			T::equals($read["module"]["is_complete"] ?? null, false, "the module does not report itself complete");

			// The inverse, on the same shape of module: the columns being there is what
			// silences it, so this can't pass by reporting the gap on everything.
			[$with_id, , $with_table] = ai_geocoding_module($with);
			$fine = $modules->aiGetModule($with_id, ai_wiring_user(2));
			$named = false;

			foreach ((array)($fine["module"]["missing_setup"] ?? []) as $line) {
				if (stripos((string)$line, "latitude") !== false) {
					$named = true;
				}
			}

			T::ok(!$named, "a module whose table has the columns is not told it is missing them");
		} finally {
			ai_geocoding_teardown($without, $without_table);
			ai_geocoding_teardown($with, $with_table);
		}
	}
