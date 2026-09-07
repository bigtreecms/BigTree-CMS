<?php
	/**
	 * Audit #28 guard E1: the referenced-entity boundary of a *queued* change.
	 *
	 * Audits #19/#24/#25/#26 asked what a write does to the world at the moment it
	 * runs. This asks the opposite question. A pending change is a write that has not
	 * run yet, and it names things — a target row, a parent page, tag ids, a module —
	 * by id. Between the moment it is queued and the moment somebody approves it,
	 * those things can be merged away, deleted, or removed from under it. Audit #28
	 * found two instances of that (A1's tag ids, A2's deleted page); this closes it as
	 * a class.
	 *
	 * `AIWriteSideEffectParityTest` is structurally blind to this: it pairs an AI seam
	 * with a REST twin and matches effect tokens inside the seam body, while the
	 * repair that is missing here lives in a different service, run by a different
	 * actor, at a different time. `AIApprovalReferentialTest` covers the 24h life of a
	 * *proposal*; a pending change has no TTL and no fingerprint.
	 *
	 * The contract: every column of bigtree_pending_changes, plus every foreign id
	 * carried *inside* its blobs, is either mapped to the method that repairs or
	 * refuses it when its target disappears, or carries an exemption reason. Adding a
	 * column without deciding which fails the enumeration leg — the whole point.
	 */

	use BigTree\Services\PageService;
	use BigTree\Services\PendingChangeService;
	use BigTree\Services\TagService;

	/**
	 * References a queued change carries, mapped to what happens when the thing they
	 * name goes away.
	 *
	 * `repair` names the method that rewrites or removes the stale reference at the
	 * moment the target is destroyed; `refuse` names the method that declines to
	 * replay it at approval. An entry must declare at least one, and every substring
	 * it declares must appear in that method's source — the structural fact that the
	 * leg exists at all.
	 *
	 * @return array<string,array{repair?:array{0:string,1:string,2:list<string>},refuse?:array{0:string,1:string,2:list<string>},why:string}>
	 */
	function ai_queued_reference_map(): array {

		return [
			// A2. Two legs, because cascadeDelete only fixes installs going forward:
			// a row already stranded before it existed has to refuse legibly.
			"item_id (bigtree_pages)" => [
				"repair" => [PageService::class, "cascadeDelete", ['SQL::delete("bigtree_pending_changes"']],
				"refuse" => [PageService::class, "publishPendingChange", ["page_deleted"]],
				"why" => "the live page an EDIT draft rewrites",
			],
			"pending_page_parent" => [
				"repair" => [PageService::class, "cascadeDelete", ["pending_page_parent"]],
				"refuse" => [PageService::class, "publishPendingChange", ["parent_page_deleted"]],
				"why" => "the parent a NEW page draft is filed under — publishing under a deleted one "
					. "created a live page in no page tree",
			],
			// The entry half, which has been complete on both counts since long before
			// audit #28 and is the shape A2's page half was measured against.
			"item_id (module entry)" => [
				"repair" => ["BigTreeAutoModule", "deleteItem", ['SQL::delete("bigtree_pending_changes"']],
				"refuse" => [PendingChangeService::class, "applyPendingChange", ["entry_deleted"]],
				"why" => "the live entry row an EDIT draft rewrites",
			],
			"module" => [
				"refuse" => [PendingChangeService::class, "applyPendingChange", ["module_unresolved"]],
				"why" => "the module definition the change's form and table belong to; deleting a module "
					. "leaves its queued changes behind, and this is what stops one replaying",
			],
			"table" => [
				"refuse" => [PendingChangeService::class, "applyPendingChange", ["module_unresolved"]],
				"why" => "the table the change writes; it is only reachable through the module, which is "
					. "resolved first — a deleted module refuses before the table is ever touched, and "
					. "deleting a module does not drop its table",
			],
			// A1. Both blob copies, repaired at the tag primitives and filtered again at
			// the replay for the writers that don't go through them.
			"tags_changes" => [
				"repair" => [TagService::class, "rewriteQueuedTagChanges", ["tags_changes"]],
				"refuse" => [PendingChangeService::class, "applyPendingChange", ["partitionExistingTagIds"]],
				"why" => "a module entry's queued tag ids",
			],
			'changes["tags"]' => [
				"repair" => [TagService::class, "rewriteQueuedTagChanges", ['$changes["tags"]']],
				"refuse" => [PageService::class, "publishPendingChange", ["partitionExistingTagIds"]],
				"why" => "a page's queued tag ids — the copy publishPendingChange actually replays, and "
					. "the one the original repair rewrote nothing of",
			],
			"publish_hook" => [
				"refuse" => [PendingChangeService::class, "applyPendingChange", ['is_callable($row["publish_hook"])']],
				"why" => "a PHP callable named by a module form; an extension that goes away leaves the "
					. "name behind, and the call is guarded rather than attempted",
			],
		];
	}

	/**
	 * References that need no repair, each with the reason. A reason here is a claim
	 * that the reference degrades harmlessly — not a note that nothing was done.
	 *
	 * @return array<string,string>
	 */
	function ai_queued_reference_exempt(): array {

		return [
			"id" => "the change's own primary key — it names nothing outside the row",
			"user" => "bigtree_pending_changes_ibfk_1 is ON DELETE CASCADE (base.sql), so a deleted "
				. "author takes the change with them at the database level",
			"date" => "a timestamp, not a reference",
			"title" => "a stored label for the queue list, not a reference",
			"type" => "NEW or EDIT, not a reference",
			"changes" => "the blob itself is not a reference; every foreign id inside it is enumerated "
				. "separately below",
			"open_graph_changes" => "holds an image resource id, which is the same soft reference a live "
				. "bigtree_open_graph row holds — a deleted file renders as no image on both, and the "
				. "queued copy is not the worse case",
			"mtm_changes" => "many-to-many tables are declared by the developer and carry no foreign key, "
				. "so a stale id inserts a relation row that every reader joins away — no fatal edge and "
				. "no user-visible artifact, unlike bigtree_tags_rel's FK",
			'changes["template"]' => "a template id is a soft reference everywhere in the CMS: a live "
				. "bigtree_pages.template names a deleted template exactly as a queued one does, and both "
				. "degrade identically (no resources, nothing required). Nothing here is specific to the queue",
			'changes["resources"] ids' => "resource ids inside the blob are tracked in "
				. "bigtree_resource_allocation, whose FK cascades on delete, and the draft's allocations "
				. "are dropped by cascadeDelete and re-keyed by reallocatePendingResources. The id left in "
				. "the blob is the same soft reference a live page's resources blob holds",
		];
	}

	/**
	 * Foreign ids carried inside the blobs, which no schema can reveal. Declared, and
	 * required to be classified, so a new one is a deliberate decision.
	 *
	 * @return list<string>
	 */
	function ai_queued_blob_references(): array {

		return ['changes["tags"]', 'changes["template"]', 'changes["resources"] ids'];
	}

	/** Column names of bigtree_pending_changes, read from base.sql's end-state DDL. */
	function ai_queued_change_columns(): array {
		static $columns = null;

		if ($columns !== null) {

			return $columns;
		}

		$sql = (string)file_get_contents(SERVER_ROOT . "core/setup/base.sql");
		preg_match('/CREATE TABLE `bigtree_pending_changes` \((.*?)\) ENGINE=/s', $sql, $match);
		preg_match_all('/`([a-z_]+)` (?:int|varchar|longtext|timestamp|text|char)/i', (string)($match[1] ?? ""), $found);
		$columns = array_values(array_unique($found[1] ?? []));

		return $columns;
	}

	/** Every reference key declared, mapped or exempt. */
	function ai_queued_declared_keys(): array {

		return array_merge(array_keys(ai_queued_reference_map()), array_keys(ai_queued_reference_exempt()));
	}

	/**
	 * A declared key normalized to the column it belongs to, so "item_id (bigtree_pages)"
	 * and "item_id (module entry)" both account for `item_id`.
	 */
	function ai_queued_key_column(string $key): string {
		$key = preg_replace('/\s*\(.*\)$/', "", $key);

		return (string)preg_replace('/\[.*$/', "", (string)$key);
	}

	/** The source of one method, for a structural assertion about it. */
	function ai_queued_method_body(string $class, string $method): string {
		if (!class_exists($class) || !method_exists($class, $method)) {

			return "";
		}

		$reflection = new ReflectionMethod($class, $method);
		$file = $reflection->getFileName();

		if (!$file || !is_readable($file)) {

			return "";
		}

		$lines = file($file);

		return implode("", array_slice(
			$lines,
			$reflection->getStartLine() - 1,
			$reflection->getEndLine() - $reflection->getStartLine() + 1
		));
	}

	function test_queued_change_every_column_is_classified() {
		$columns = ai_queued_change_columns();
		T::ok(count($columns) > 5, "bigtree_pending_changes' columns were parsed from base.sql");

		$accounted = array_map("ai_queued_key_column", ai_queued_declared_keys());
		$unclassified = array_values(array_diff($columns, $accounted));

		T::equals(
			implode(", ", $unclassified),
			"",
			"every bigtree_pending_changes column is mapped to a repair/refusal or exempt with a reason"
		);
	}

	function test_queued_change_every_blob_reference_is_classified() {
		$declared = ai_queued_declared_keys();
		$missing = [];

		foreach (ai_queued_blob_references() as $reference) {
			if (!in_array($reference, $declared, true)) {
				$missing[] = $reference;
			}
		}

		T::equals(implode(", ", $missing), "", "every foreign id carried inside a change blob is classified");
	}

	/** Staleness the other way: nothing is classified that isn't a real reference. */
	function test_queued_change_no_stale_classification() {
		$columns = ai_queued_change_columns();
		$blobs = ai_queued_blob_references();
		$stale = [];

		foreach (ai_queued_declared_keys() as $key) {
			if (in_array($key, $blobs, true)) {

				continue;
			}

			if (!in_array(ai_queued_key_column($key), $columns, true)) {
				$stale[] = $key;
			}
		}

		T::equals(
			implode(", ", $stale),
			"",
			"no classification names a column or blob key that no longer exists"
		);
	}

	function test_queued_change_no_key_is_both_mapped_and_exempt() {
		$both = array_values(array_intersect(
			array_keys(ai_queued_reference_map()),
			array_keys(ai_queued_reference_exempt())
		));

		T::equals(implode(", ", $both), "", "no reference is both repaired and exempt");
	}

	function test_queued_change_every_exemption_carries_a_reason() {
		$empty = [];

		foreach (ai_queued_reference_exempt() as $key => $reason) {
			if (strlen(trim((string)$reason)) < 25) {
				$empty[] = $key;
			}
		}

		T::equals(implode(", ", $empty), "", "every exemption states why the reference degrades harmlessly");
	}

	/**
	 * The load-bearing leg: each mapped reference actually names a method whose source
	 * carries the repair or the refusal. This is what fails when a fix is reverted.
	 */
	function test_queued_change_every_mapped_reference_is_repaired_or_refused() {
		$missing = [];

		foreach (ai_queued_reference_map() as $key => $entry) {
			T::ok(
				isset($entry["repair"]) || isset($entry["refuse"]),
				"{$key} declares a repair or a refusal"
			);
			T::ok(strlen(trim((string)($entry["why"] ?? ""))) > 10, "{$key} says what it names");

			foreach (["repair", "refuse"] as $leg) {
				if (!isset($entry[$leg])) {

					continue;
				}

				[$class, $method, $contains] = $entry[$leg];
				$body = ai_queued_method_body($class, $method);

				if ($body === "") {
					$missing[] = "{$key}: {$class}::{$method} could not be read";

					continue;
				}

				foreach ($contains as $needle) {
					if (strpos($body, $needle) === false) {
						$missing[] = "{$key} ({$leg}): {$method} does not contain \"{$needle}\"";
					}
				}
			}
		}

		T::equals(
			implode("; ", $missing),
			"",
			"every queued reference is repaired at the source or refused at the replay"
		);
	}
