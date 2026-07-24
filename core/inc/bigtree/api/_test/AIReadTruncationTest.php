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
