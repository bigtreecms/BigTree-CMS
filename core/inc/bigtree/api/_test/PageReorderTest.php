<?php
	use BigTree\Services\PageService;

	/**
	 * Guards PageService::reorder against the pending-draft id collision.
	 *
	 * Pending NEW pages live only in bigtree_pending_changes and are presented
	 * with their pending-change id as the row id. That id shares the same integer
	 * namespace as real bigtree_pages ids, so if a draft slipped into a reorder
	 * payload the server would rewrite the position of whatever live page happened
	 * to share that integer (or an unrelated page in another folder).
	 *
	 * reorder() resolves the real child ids of the folder via a DB query, then
	 * delegates the corruption guard to the pure helper PageService::filterReorderableIds.
	 * These tests exercise that helper directly — no database required — so the
	 * filtering logic is covered in the standalone harness.
	 */

	function test_reorder_drops_pending_and_foreign_ids() {
		// $childIds is what the SELECT returns: the genuine children of the folder.
		// The requested order includes ids that are NOT children — a pending-change
		// id (777) and a page from another folder (888). Both must be dropped while
		// the real children keep their requested relative order.
		$childIds = [10, 11, 12];
		$requestedIds = [11, 777, 10, 888, 12];

		$reorderable = PageService::filterReorderableIds($childIds, $requestedIds);

		T::equals($reorderable, [11, 10, 12], "pending/foreign ids dropped, real children keep relative order");
	}

	function test_reorder_keeps_all_valid_ids_unchanged() {
		// When every requested id is a real child, the list passes through verbatim.
		$childIds = [10, 11, 12];
		$requestedIds = [12, 10, 11];

		$reorderable = PageService::filterReorderableIds($childIds, $requestedIds);

		T::equals($reorderable, [12, 10, 11], "all-valid list is unchanged");
	}

	function test_reorder_normalizes_id_types() {
		// The request layer hands string ids; the helper must compare numerically
		// so a stringy "11" still matches integer child 11.
		$childIds = [10, 11];
		$requestedIds = ["11", "10"];

		$reorderable = PageService::filterReorderableIds($childIds, $requestedIds);

		T::equals($reorderable, [11, 10], "stringy ids are normalized to ints and matched");
	}
