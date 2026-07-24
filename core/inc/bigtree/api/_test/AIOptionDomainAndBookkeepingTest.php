<?php
	/**
	 * Audit #7 Phase 2: option-domain parity (E5) and relation bookkeeping (E6).
	 *
	 * E5 — audit #5 gave module entries a `list` option domain; the page-content path
	 * never got it, so a `list`/`select`/`radio` template field accepted any string.
	 * Both paths now call the one FieldOptionDomain helper, so the guard is that (a)
	 * the helper behaves and (b) both sift seams and both schema seams actually call
	 * it — the divergence is what created the gap in the first place.
	 *
	 * E6 — the entry write path recomputes bigtree_tags.usage_count on create/update
	 * (it has since 2018) but entry *deletion* orphaned every tag relation and Open
	 * Graph row and left the count inflated. deleteItem now tears both down and
	 * recomputes, matching the page delete path.
	 */

	use BigTree\Services\AI\FieldOptionDomain;

	/** E5: the shared option-domain helper refuses an out-of-domain value. */
	function test_option_domain_refuses_out_of_domain_value() {
		$field = [
			"title" => "Region",
			"options" => [
				["value" => "pdx", "label" => "Portland"],
				["value" => "sea", "label" => "Seattle"],
			],
		];

		$value = "Nowhere";
		$error = FieldOptionDomain::violation($field, $value);
		T::ok($error !== null, "a value matching no option is refused");
		T::ok(strpos((string)$error, "Portland") !== false, "and the refusal lists the choices");

		// A stored value passes untouched.
		$value = "pdx";
		T::equals(FieldOptionDomain::violation($field, $value), null, "a stored value passes");
		T::equals($value, "pdx", "and is left as-is");

		// A recognisable label is rewritten to its stored value in place.
		$value = "Seattle";
		T::equals(FieldOptionDomain::violation($field, $value), null, "a label is accepted");
		T::equals($value, "sea", "and rewritten to the stored value the admin would store");

		// No options resolved → nothing to check (a misconfigured db list isn't a
		// reason to refuse every write).
		$value = "anything";
		T::equals(FieldOptionDomain::violation(["options" => []], $value), null, "no options means no check");

		// An empty value is `required`'s business.
		$value = "";
		T::equals(FieldOptionDomain::violation($field, $value), null, "an empty value is left to the required gate");
	}

	/**
	 * E5: the page-content path and the entry-data path resolve and check options
	 * through the same helper — the structural guarantee they cannot diverge again.
	 */
	function test_option_domain_both_paths_use_the_shared_helper() {
		$page_sift = ai_surface_method_body(\BigTree\Services\PageService::class, "aiSiftResourceContent");
		$entry_sift = ai_surface_method_body(\BigTree\Services\AutoModuleService::class, "aiSiftEntryData");

		T::ok(strpos($page_sift, "FieldOptionDomain::violation") !== false, "page content sift checks the option domain");
		T::ok(
			strpos($entry_sift, "aiOptionViolation") !== false || strpos($entry_sift, "FieldOptionDomain::violation") !== false,
			"entry data sift checks the option domain"
		);

		$page_schema = ai_surface_method_body(\BigTree\Services\PageService::class, "aiTemplateResourceSchema");
		T::ok(
			strpos($page_schema, "FieldOptionDomain::resolve") !== false,
			"page template schema resolves options (so the sift has a domain to check)"
		);

		// And the entry side's helpers delegate to the shared class, not a private copy.
		$entry_options = ai_surface_method_body(\BigTree\Services\AutoModuleService::class, "aiOptionViolation");
		T::ok(
			strpos($entry_options, "FieldOptionDomain::violation") !== false,
			"the entry option check delegates to the shared helper"
		);

		// The schema-discovery error names each list field's choices, so a model that
		// sent an out-of-domain value can correct it from the same message (audit #7 B1).
		$describe = ai_surface_method_body(\BigTree\Services\PageService::class, "aiDescribeResourceSchema");
		T::ok(
			strpos($describe, "options") !== false,
			"the page schema description surfaces list-field options"
		);
	}

	/**
	 * E6: after a create carrying a tag, bigtree_tags.usage_count matches the actual
	 * relation count; after the entry is deleted it drops back, and no relation or
	 * Open Graph row is orphaned.
	 */
	function test_relation_bookkeeping_on_entry_create_and_delete() {
		if (!function_exists("parity_ai_processors_ready") || !parity_ai_processors_ready()) {

			return;
		}

		$table = "timber_news";
		$tag = "zz-audit7-" . bin2hex(random_bytes(4));
		$tag_id = (int)SQL::insert("bigtree_tags", [
			"tag" => $tag,
			"route" => $tag,
			// Deliberately wrong, to prove the write path recomputes rather than trusts it.
			"usage_count" => 99,
		]);
		$entry_id = 0;

		try {
			$entry_id = (int)\BigTreeAutoModule::createItem($table, ["title" => "ZZ Audit7 Bookkeeping"], [], [$tag_id]);
			T::ok($entry_id > 0, "entry created");

			$rel = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ?", $tag_id);
			$count = (int)SQL::fetchSingle("SELECT usage_count FROM bigtree_tags WHERE id = ?", $tag_id);
			T::equals($count, $rel, "usage_count equals the actual relation count after create");
			T::equals($count, 1, "and reflects the one entry that carries the tag (not the stale 99)");

			// Give the entry an Open Graph row, as a real entry with social metadata has.
			SQL::insert("bigtree_open_graph", [
				"table" => $table,
				"entry" => (string)$entry_id,
				"type" => "article",
				"title" => "OG",
				"description" => "",
				"image" => "",
			]);

			\BigTreeAutoModule::deleteItem($table, $entry_id);

			$rel_after = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_tags_rel WHERE `table` = ? AND entry = ?", $table, (string)$entry_id
			);
			$og_after = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_open_graph WHERE `table` = ? AND entry = ?", $table, (string)$entry_id
			);
			$count_after = (int)SQL::fetchSingle("SELECT usage_count FROM bigtree_tags WHERE id = ?", $tag_id);

			T::equals($rel_after, 0, "deleting the entry leaves no orphan tag relation");
			T::equals($og_after, 0, "and no orphan Open Graph row");
			T::equals($count_after, 0, "and usage_count is recomputed down, not left inflated");
			$entry_id = 0;
		} finally {
			if ($entry_id > 0) {
				parity_delete_news_entries($entry_id);
			}

			SQL::query("DELETE FROM bigtree_tags_rel WHERE tag = ?", $tag_id);
			SQL::delete("bigtree_tags", $tag_id);
		}
	}

	/** E6: an update that changes the tag set keeps usage_count exact for both tags. */
	function test_relation_bookkeeping_on_entry_update() {
		if (!function_exists("parity_ai_processors_ready") || !parity_ai_processors_ready()) {

			return;
		}

		$table = "timber_news";
		$tag_a = "zz-audit7a-" . bin2hex(random_bytes(4));
		$tag_b = "zz-audit7b-" . bin2hex(random_bytes(4));
		$id_a = (int)SQL::insert("bigtree_tags", ["tag" => $tag_a, "route" => $tag_a, "usage_count" => 0]);
		$id_b = (int)SQL::insert("bigtree_tags", ["tag" => $tag_b, "route" => $tag_b, "usage_count" => 0]);
		$entry_id = 0;

		try {
			$entry_id = (int)\BigTreeAutoModule::createItem($table, ["title" => "ZZ Audit7 Update"], [], [$id_a]);
			T::ok($entry_id > 0, "entry created with tag A");

			// Move the entry from tag A to tag B.
			\BigTreeAutoModule::updateItem($table, $entry_id, ["title" => "ZZ Audit7 Update 2"], [], [$id_b]);

			$count_a = (int)SQL::fetchSingle("SELECT usage_count FROM bigtree_tags WHERE id = ?", $id_a);
			$count_b = (int)SQL::fetchSingle("SELECT usage_count FROM bigtree_tags WHERE id = ?", $id_b);
			T::equals($count_a, 0, "the dropped tag's count falls to zero");
			T::equals($count_b, 1, "the added tag's count rises to one");
		} finally {
			if ($entry_id > 0) {
				parity_delete_news_entries($entry_id);
			}

			SQL::query("DELETE FROM bigtree_tags_rel WHERE tag IN (?, ?)", $id_a, $id_b);
			SQL::delete("bigtree_tags", $id_a);
			SQL::delete("bigtree_tags", $id_b);
		}
	}
