<?php
	/**
	 * Audit #7 Phase 4 (E4): the read-truncation contract.
	 *
	 * Audit #5 (C3) established the rule for a capped read seam: filter by permission
	 * *before* the cap, and tell the model the result is partial. list_module_entries
	 * followed it; get_page_tree, list_resources, search_files and get_settings did
	 * not — they capped silently, so "there is no page/file/setting called X" could
	 * be answered from a truncated scan, and the model's next move (create a
	 * duplicate) followed from a false premise.
	 *
	 * This guards the rule structurally (every capped read seam returns a truncation
	 * key) and behaviourally (get_page_tree and get_settings actually set it).
	 */

	use BigTree\Services\PageService;
	use BigTree\Services\SettingService;

	/**
	 * The capped ai* read seams, each mapped to the truncation key its payload must
	 * carry. A new capped read seam added without a marker fails here.
	 *
	 * @return array<string,array{0:string,1:string}> label => [class, method]
	 */
	function ai_read_truncation_seams(): array {

		return [
			"get_page_tree" => [\BigTree\Services\PageService::class, "aiPageTree"],
			"list_module_entries" => [\BigTree\Services\AutoModuleService::class, "aiListEntries"],
			"list_resources" => [\BigTree\Services\ResourceService::class, "aiListResources"],
			"search_files" => [\BigTree\Services\ResourceService::class, "aiSearchFiles"],
			"get_settings" => [\BigTree\Services\SettingService::class, "aiGetSettings"],
		];
	}

	/** E4: every capped read seam returns a has_more marker. */
	function test_every_capped_read_seam_marks_truncation() {
		$missing = [];

		foreach (ai_read_truncation_seams() as $label => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$label}: {$method} source was read");

			// A LIMIT or a break-at-limit without a has_more key is the silent-cliff
			// shape the audit found.
			$has_marker = strpos($body, '"has_more"') !== false;
			$is_capped = stripos($body, "LIMIT") !== false
				|| strpos($body, "array_slice") !== false
				|| strpos($body, "break;") !== false;

			if ($is_capped && !$has_marker) {
				$missing[] = $label;
			}
		}

		T::equals(implode(", ", $missing), "", "every capped read seam carries a has_more truncation marker");
	}

	/** E4: get_page_tree returns has_more/offset, and filters before the cap. */
	function test_page_tree_reports_truncation() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$svc = new PageService();
		$dev = (object)["id" => 1, "level" => 2, "permissions" => []];
		$tree = $svc->aiPageTree(0, $dev, 0);

		T::ok(!isset($tree["error"]), "the site root tree resolves for a developer");
		T::ok(array_key_exists("has_more", $tree), "the payload carries has_more");
		T::ok(is_bool($tree["has_more"]), "has_more is a boolean");
		T::ok(array_key_exists("offset", $tree), "and an offset the model can advance");

		// The filter-before-cap ordering: the source over-fetches, filters by view
		// access, then slices — so the cap is applied to viewable rows, not raw rows.
		$body = ai_surface_method_body(PageService::class, "aiPageTree");
		$filter_pos = strpos($body, "array_filter");
		$slice_pos = strpos($body, "array_slice");
		T::ok($filter_pos !== false && $slice_pos !== false, "aiPageTree both filters and slices");
		T::ok($filter_pos < $slice_pos, "and filters before it slices (filter-before-cap)");
	}

	/**
	 * Audit #10 E1 — the *value* truncation axis.
	 *
	 * The map above is about row-count truncation: "there were more entries than I
	 * showed you". Cutting an individual field's value — "this entry's body is
	 * longer than I showed you" — is a different axis the has_more guard has no
	 * concept of, and it is the dangerous one, because every entry/page write tool
	 * replaces a field's value wholesale. get_module_entry cut every column at 500
	 * characters with a bare "…" and no marker at all, so a read-modify-write of any
	 * real body silently deleted everything past the cut.
	 *
	 * Each seam maps to the payload key that discloses which values were cut, or an
	 * explicit "exempt: reason". A seam that caps a value and discloses nothing
	 * fails.
	 *
	 * @return array<string,array{0:string,1:string,2:string}> label => [class, method, key]
	 */
	function ai_value_truncation_seams(): array {

		return [
			"get_module_entry" => [
				\BigTree\Services\SearchService::class, "getModuleEntryDetail", "fields_truncated",
			],
			"list_module_entries" => [
				\BigTree\Services\AutoModuleService::class, "aiListEntries", "fields_truncated",
			],
			"get_page (content)" => [
				\BigTree\Services\PageService::class, "aiPageContentFields", "content_truncated",
			],
			// get_page's flattened-prose companion to the keyed content map. Its cap
			// lives in plainTextFromResources, so the disclosure is asserted on the seam
			// that emits the payload rather than on the one that does the cutting.
			"get_page (content_text)" => [
				\BigTree\Services\SearchService::class, "getPageDetail", "content_text_truncated",
			],
			// The one entry read seam with no per-value cap of its own: it returns the
			// module view's own cached summary columns (column1…column4), not the
			// editable field values, and no write tool takes its output back.
			"search_module_entries" => [
				\BigTree\Services\SearchService::class, "searchModuleEntries", "exempt: returns view-cache summary columns, not editable values",
			],
		];
	}

	/**
	 * E1: every read seam that caps an individual value says which values it cut.
	 *
	 * A mapped seam must carry its disclosure key outright — not only when its own
	 * body happens to contain the `mb_substr` that does the cutting. get_page's
	 * content_text is the case that forced this: the cap is one call down in
	 * plainTextFromResources, so a body-local test would have passed the seam
	 * vacuously while it disclosed nothing.
	 */
	function test_every_value_capping_read_seam_discloses_it() {
		$missing = [];

		foreach (ai_value_truncation_seams() as $label => [$class, $method, $key]) {
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$label}: {$method} source was read");

			if (strpos($key, "exempt:") === 0) {
				T::ok(
					strpos($body, "mb_substr") === false,
					"{$label} is exempt and really does not cap a value"
				);

				continue;
			}

			if (strpos($body, "\"{$key}\"") === false) {
				$missing[] = "{$label} (expected {$key})";
			}
		}

		T::equals(implode(", ", $missing), "", "every value-capping read seam discloses which values it cut");
	}

	/** E1: entries are read at the same budget pages are, not one-twelfth of it. */
	function test_entry_and_page_read_caps_match() {
		T::equals(
			\BigTree\Services\AutoModuleService::AI_ENTRY_READ_CAP,
			\BigTree\Services\PageService::AI_CONTENT_FIELD_CAP,
			"an entry body is read at the same cap as a page body"
		);
		T::ok(
			\BigTree\Services\AutoModuleService::AI_ENTRY_READ_CAP >= 6000,
			"and that cap is big enough for a real body field"
		);
	}

	/**
	 * E1: a value handed back as it came out of a truncated read is refused, and a
	 * genuine edit is not.
	 */
	function test_truncated_read_writeback_is_refused() {
		$cap = 200;
		$stored = str_repeat("The gala raised a great deal of money. ", 20);
		T::ok(mb_strlen($stored) > $cap, "the fixture body is longer than the cap");

		$as_read = mb_substr($stored, 0, $cap);

		T::ok(
			\BigTree\Services\AI\TruncatedRead::violation("Body", $as_read, $stored, [$cap]) !== null,
			"writing back exactly what the read showed is refused"
		);
		T::ok(
			\BigTree\Services\AI\TruncatedRead::violation("Body", $as_read . "…", $stored, [$cap]) !== null,
			"and the refusal survives the model copying the ellipsis back"
		);

		// A typo fixed near the start still ends where the cut fell.
		$edited = "The Gala" . mb_substr($as_read, 8);
		T::ok(
			\BigTree\Services\AI\TruncatedRead::violation("Body", $edited, $stored, [$cap]) !== null,
			"an edit made inside the visible part is refused too — it still ends at the cut"
		);

		// A genuine full-value edit ends where the author chose, not where the cap fell.
		T::equals(
			\BigTree\Services\AI\TruncatedRead::violation("Body", $stored . " And more.", $stored, [$cap]),
			null,
			"appending to the whole stored value is allowed"
		);
		T::equals(
			\BigTree\Services\AI\TruncatedRead::violation("Body", "A completely rewritten body for the gala entry.", $stored, [$cap]),
			null,
			"a rewrite is allowed"
		);
		T::equals(
			\BigTree\Services\AI\TruncatedRead::violation("Body", $stored, $stored, [$cap]),
			null,
			"writing the value back unchanged is allowed"
		);
		T::equals(
			\BigTree\Services\AI\TruncatedRead::violation("Body", $as_read, $as_read, [$cap]),
			null,
			"and a short field that was never cut is never refused"
		);
	}

	/** E4: get_settings sets has_more when the scan is truncated. */
	function test_get_settings_reports_truncation() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$svc = new SettingService();
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];

		$all = $svc->aiGetSettings("", 1000, $admin);

		if (empty($all["settings"]) || count($all["settings"]) < 2) {

			// Too few settings to prove truncation; the structural guard still covers it.
			return;
		}

		$capped = $svc->aiGetSettings("", 1, $admin);
		T::equals(count($capped["settings"]), 1, "a limit of 1 returns one setting");
		T::ok(!empty($capped["has_more"]), "and has_more is set because more settings exist");
	}
