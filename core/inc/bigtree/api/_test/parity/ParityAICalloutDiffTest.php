<?php
	/**
	 * Audit #9 C1/C2: the blast-radius disclosure on a callout field edit.
	 *
	 * The template diff has counted its affected pages since audit #7; the callout
	 * diff said only "orphaned wherever this callout is already placed" — the
	 * abstract consequence with no sense of scale, on the developer object that is
	 * typically embedded in far more places than a template is applied to. Silence
	 * there reads as "small".
	 *
	 * Two things have to hold for the count to mean anything, and each is a way the
	 * disclosure was previously absent rather than merely imprecise:
	 *
	 *   - it has to see both JSON spacings, because bigtree_pages.resources is
	 *     written by two encoders (json_encode on the new API, BigTree::json —
	 *     JSON_PRETTY_PRINT — from the legacy admin) and real databases hold a mix;
	 *   - a field that already existed and was flipped to required has to report,
	 *     not just one newly added.
	 */

	use BigTree\Services\CalloutService;

	/** Call the private diff builder directly. */
	function parity_callout_field_diff(array $before, array $after, string $callout_id): array {
		$ref = new ReflectionMethod(CalloutService::class, "aiCalloutFieldDiff");
		$ref->setAccessible(true);

		return $ref->invokeArgs(new CalloutService(), [$before, $after, $callout_id]);
	}

	function parity_callout_field(string $id, string $type = "text", bool $required = false): array {

		return [
			"id" => $id,
			"type" => $type,
			"title" => ucfirst($id),
			"subtitle" => "",
			"settings" => $required ? ["validation" => "required"] : [],
		];
	}

	/**
	 * A page whose resources blob places $callout_id, encoded the way $pretty asks
	 * for — the two forms the column actually holds.
	 */
	function parity_seed_page_placing_callout(string $callout_id, bool $pretty): int {
		$resources = ["sidebar" => [["type" => $callout_id, "headline" => "ZZ parity"]]];
		$flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0;

		return parity_seed_page(["resources" => json_encode($resources, $flags)]);
	}

	function test_parity_ai_callout_usage_count_sees_both_json_spacings() {
		if (!parity_db_available()) {
			return;
		}

		$callout_id = "zzdiff" . bin2hex(random_bytes(3));
		$compact = 0;
		$pretty = 0;

		try {
			$compact = parity_seed_page_placing_callout($callout_id, false);
			$pretty = parity_seed_page_placing_callout($callout_id, true);

			$before = [parity_callout_field("headline"), parity_callout_field("blurb")];
			$after = [parity_callout_field("headline")];
			$diff = parity_callout_field_diff($before, $after, $callout_id);

			T::ok(isset($diff["pages_using_callout"]), "the usage count reaches the card");
			T::ok(
				strpos((string)$diff["pages_using_callout"], "at least 2 pages") !== false,
				"and counts the legacy pretty-printed placement as well as the compact one"
			);
			T::ok(
				strpos((string)$diff["fields_removed"], "at least 2 pages") !== false,
				"the removal row carries the scale rather than only the abstraction"
			);
		} finally {
			parity_delete_page($compact);
			parity_delete_page($pretty);
		}
	}

	/** No placements at all: the phrase is omitted rather than claiming zero. */
	function test_parity_ai_callout_usage_is_silent_when_nothing_places_it() {
		if (!parity_db_available()) {
			return;
		}

		$before = [parity_callout_field("headline"), parity_callout_field("blurb")];
		$after = [parity_callout_field("headline")];
		$diff = parity_callout_field_diff($before, $after, "zzunplaced" . bin2hex(random_bytes(3)));

		T::ok(!isset($diff["pages_using_callout"]), "an unplaced callout gets no count row");
		T::ok(isset($diff["fields_removed"]), "the orphaning consequence is still stated");
	}

	/**
	 * C2, the case the row was written for: required → optional was reported, but
	 * optional → required on a field that already exists was collected nowhere, so
	 * the disclosure never fired for the edit most likely to block existing content.
	 */
	function test_parity_ai_callout_diff_flags_an_existing_field_turned_required() {
		if (!parity_db_available()) {
			return;
		}

		$before = [parity_callout_field("headline"), parity_callout_field("blurb")];
		$after = [parity_callout_field("headline", "text", true), parity_callout_field("blurb")];

		$diff = parity_callout_field_diff($before, $after, "");

		T::ok(isset($diff["now_required"]), "an existing field turned required is surfaced");
		T::ok(strpos((string)$diff["now_required"], "headline") !== false, "the field is named");
		T::ok(
			strpos((string)$diff["now_required"], "can't be saved again") !== false,
			"and the consequence is spelled out, not just the field name"
		);
	}

	function test_parity_ai_callout_diff_is_quiet_when_nothing_structural_changed() {
		if (!parity_db_available()) {
			return;
		}

		$fields = [parity_callout_field("headline", "text", true), parity_callout_field("blurb")];
		$diff = parity_callout_field_diff($fields, $fields, "");

		foreach (["fields_added", "fields_removed", "fields_retyped", "no_longer_required", "now_required"] as $key) {
			T::ok(!isset($diff[$key]), "{$key} is omitted when nothing changed");
		}
	}
