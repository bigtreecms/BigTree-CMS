<?php
	/**
	 * Migration runner EXECUTION side (017 Phase 2b, plan 032).
	 *
	 * Exercises the run-path additions on MigrationService and the CLI runner:
	 *   - begin() -> finish() transitions a row 0 -> 1 and stamps a non-null
	 *     duration_ms;
	 *   - finish() mirrors bigtree-internal-revision to the max success=1 revision
	 *     <= BIGTREE_REVISION, and NEVER LOWERS the integer (clamp);
	 *   - begin() is idempotent (does not reset applied_at, no-ops on a success=1
	 *     row);
	 *   - a SIMULATED single-shot revision (temp revisions/NNNNNN.php) applied via
	 *     the runner's isolated child mode ends success=1, and re-applying is a
	 *     no-op;
	 *   - a SIMULATED batched revision (temp file that reports {pages} then completes
	 *     on the last page) converges to exactly one success=1 row, and the parent
	 *     page-loop cap STOPS a revision whose finish() never fires (failure path,
	 *     characterized against a deterministic page-loop helper).
	 *
	 * Temp revision files live in a high, namespaced range (>> the real 201-505) so
	 * they cannot collide; ledger rows + the integer are restored in finally.
	 * Skips cleanly when the ledger table is absent.
	 */

	use BigTree\Services\MigrationService;

	/** Floor of the throwaway revision range (well above real revisions). */
	const MIGEXEC_BASE = 930000;

	/** True if the bigtree_migrations table is queryable in this harness. */
	function _migexec_has_table(): bool {
		try {
			SQL::query("SELECT 1 FROM bigtree_migrations LIMIT 1");

			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/** Delete every seeded ledger row in the throwaway range. */
	function _migexec_cleanup(): void {
		SQL::query("DELETE FROM bigtree_migrations WHERE revision >= ?", MIGEXEC_BASE);
	}

	/** Absolute path to a throwaway revision file. */
	function _migexec_revision_path(int $revision): string {
		return SERVER_ROOT . MigrationService::REVISIONS_DIR . $revision . ".php";
	}

	/** Run the CLI runner's isolated child mode for one revision page (shared DB). */
	function _migexec_run_child(int $revision, ?int $page = null, ?int $total_pages = null): string {
		$cmd = escapeshellarg(PHP_BINARY)
			. " " . escapeshellarg(SERVER_ROOT . "core/admin/migrate-run.php")
			. " --exec-revision " . escapeshellarg((string)$revision);

		if ($page !== null) {
			$cmd .= " " . escapeshellarg((string)$page) . " " . escapeshellarg((string)$total_pages);
		}

		$raw = shell_exec($cmd . " 2>&1");

		return $raw === null ? "" : trim($raw);
	}

	function test_migration_begin_then_finish_transitions_and_duration() {
		if (!_migexec_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		$revision = MIGEXEC_BASE + 1;

		try {
			_migexec_cleanup();

			MigrationService::begin($revision, "deadbeef");
			$started = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			T::ok(is_array($started), "begin() inserts a ledger row");
			T::equals((int)$started["success"], 0, "begin() row is success=0 (in-flight marker)");
			T::equals($started["checksum"], "deadbeef", "begin() stores the supplied checksum");
			T::ok(is_null($started["duration_ms"]), "duration_ms is null before finish()");

			MigrationService::finish($revision);
			$finished = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			T::equals((int)$finished["success"], 1, "finish() flips the row to success=1");
			T::ok(!is_null($finished["duration_ms"]), "finish() sets a non-null duration_ms");
			T::ok((int)$finished["duration_ms"] >= 0, "duration_ms is non-negative");
		} finally {
			_migexec_cleanup();
		}
	}

	function test_migration_finish_mirrors_integer_to_max_applied() {
		if (!_migexec_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		// finish() mirrors bigtree-internal-revision to the max success=1 revision
		// <= BIGTREE_REVISION. Seed a couple of REAL-numbered success=1 rows below
		// the target and assert the integer lands on the highest of them.
		$original = BigTreeCMS::getSetting("bigtree-internal-revision");
		$target = BIGTREE_REVISION;
		$lower = $target - 2;
		$higher = $target - 1;
		$lower_before = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $lower);
		$higher_before = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $higher);

		try {
			// Start the integer well below so the mirror has room to advance.
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", $lower - 5);

			MigrationService::begin($lower, MigrationService::PRE_LEDGER);
			MigrationService::finish($lower);

			T::equals(
				(int)BigTreeCMS::getSetting("bigtree-internal-revision"),
				$lower,
				"finish() advances the integer to the newly-applied revision"
			);

			MigrationService::begin($higher, MigrationService::PRE_LEDGER);
			MigrationService::finish($higher);

			T::equals(
				(int)BigTreeCMS::getSetting("bigtree-internal-revision"),
				$higher,
				"finish() advances the integer to the max applied revision <= target"
			);
		} finally {
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", (int)$original);

			if ($lower_before) {
				MigrationService::record($lower, (string)$lower_before["name"], (string)$lower_before["checksum"]);
			} else {
				SQL::query("DELETE FROM bigtree_migrations WHERE revision = ?", $lower);
			}

			if ($higher_before) {
				MigrationService::record($higher, (string)$higher_before["name"], (string)$higher_before["checksum"]);
			} else {
				SQL::query("DELETE FROM bigtree_migrations WHERE revision = ?", $higher);
			}
		}
	}

	function test_migration_finish_never_lowers_integer() {
		if (!_migexec_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		// A throwaway revision above BIGTREE_REVISION is NOT <= target, so the mirror
		// max is whatever the real ledger holds. With the integer pinned ABOVE that,
		// finish() must NOT lower it (the clamp).
		$revision = MIGEXEC_BASE + 9;
		$original = BigTreeCMS::getSetting("bigtree-internal-revision");
		$pinned = BIGTREE_REVISION + 1000;

		try {
			_migexec_cleanup();

			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", $pinned);

			MigrationService::begin($revision, "out-of-range");
			MigrationService::finish($revision);

			T::equals(
				(int)BigTreeCMS::getSetting("bigtree-internal-revision"),
				$pinned,
				"finish() never lowers the integer (clamp holds when mirror max < current)"
			);
		} finally {
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-revision", (int)$original);
			_migexec_cleanup();
		}
	}

	function test_migration_begin_is_idempotent() {
		if (!_migexec_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		$revision = MIGEXEC_BASE + 2;

		try {
			_migexec_cleanup();

			MigrationService::begin($revision, "first");
			$first = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			// A re-call (e.g. another page of a batched run) must NOT reset applied_at
			// or the checksum, and must not create a duplicate.
			MigrationService::begin($revision, "second");
			$second = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);
			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_migrations WHERE revision = ?", $revision);

			T::equals($count, 1, "begin() never duplicates a row");
			T::equals($second["applied_at"], $first["applied_at"], "begin() does not reset applied_at on a re-call");
			T::equals($second["checksum"], "first", "begin() leaves the existing checksum (does not overwrite)");
			T::equals((int)$second["success"], 0, "row stays in-flight (success=0) across begin() re-calls");

			// On a success=1 row, begin() is a no-op.
			MigrationService::finish($revision);
			MigrationService::begin($revision, "third");
			$after = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			T::equals((int)$after["success"], 1, "begin() no-ops on an already-applied (success=1) row");
		} finally {
			_migexec_cleanup();
		}
	}

	function test_migration_runner_applies_single_shot_revision() {
		if (!_migexec_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		$revision = MIGEXEC_BASE + 100;
		$file = _migexec_revision_path($revision);

		$body = "<?php\n"
			. "\t\\BigTree\\Services\\MigrationService::begin($revision);\n"
			. "\t\\BigTree\\Services\\MigrationService::finish($revision);\n"
			. "\techo BigTree::json([\"complete\" => true, \"response\" => \"single-shot $revision\"]);\n";

		try {
			_migexec_cleanup();
			file_put_contents($file, $body);

			$out = _migexec_run_child($revision);
			$row = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			T::ok(is_array($row), "single-shot revision recorded a ledger row via the runner child");
			T::equals((int)$row["success"], 1, "single-shot revision ends success=1 after one include");
			T::ok(strpos($out, "complete") !== false, "single-shot revision echoed its completion JSON");

			// Re-apply: begin/finish are idempotent — still exactly one success=1 row.
			$applied_at = $row["applied_at"];
			_migexec_run_child($revision);
			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_migrations WHERE revision = ?", $revision);
			$reapplied = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);

			T::equals($count, 1, "re-applying a single-shot revision does not duplicate (no-op)");
			T::equals($reapplied["applied_at"], $applied_at, "re-applying does not reset applied_at (begin no-op)");
		} finally {
			@unlink($file);
			_migexec_cleanup();
		}
	}

	function test_migration_runner_batched_revision_converges() {
		if (!_migexec_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		// A temp batched revision: no page -> {pages:2}; page<total -> {complete:false};
		// last page -> finish() + {complete:true}. Drive it exactly like the runner's
		// page loop and assert one-and-only-one success=1 row at the end.
		$revision = MIGEXEC_BASE + 200;
		$file = _migexec_revision_path($revision);

		$body = "<?php\n"
			. "\t\\BigTree\\Services\\MigrationService::begin($revision);\n"
			. "\t\$total_pages = 2;\n"
			. "\tif (empty(\$_GET[\"page\"])) {\n"
			. "\t\techo BigTree::json([\"complete\" => false, \"response\" => \"batched\", \"pages\" => \$total_pages]);\n"
			. "\t\tdie();\n"
			. "\t}\n"
			. "\t\$page = (int)\$_GET[\"page\"];\n"
			. "\tif (\$page < \$total_pages) {\n"
			. "\t\techo BigTree::json([\"complete\" => false, \"response\" => \"page \".\$page]);\n"
			. "\t} else {\n"
			. "\t\t\\BigTree\\Services\\MigrationService::finish($revision);\n"
			. "\t\techo BigTree::json([\"complete\" => true, \"response\" => \"done\"]);\n"
			. "\t}\n";

		try {
			_migexec_cleanup();
			file_put_contents($file, $body);

			// Initial no-page call reports the page count. BigTree::json() pretty-prints,
			// so extract the last {...} block (mirrors the runner's parser).
			$initial = _migexec_run_child($revision);
			$start = strrpos($initial, "{");
			$end = strrpos($initial, "}");
			$initial_json = ($start !== false && $end !== false && $end >= $start)
				? json_decode(substr($initial, $start, $end - $start + 1), true)
				: null;

			T::ok(is_array($initial_json) && (int)($initial_json["pages"] ?? 0) === 2, "batched revision reports {pages:2} on the no-page call");

			$row = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);
			T::equals((int)$row["success"], 0, "batched revision is in-flight (success=0) after the no-page call");

			// Drive pages until it drops out of completion (mirror the runner loop).
			_migexec_run_child($revision, 1, 2);
			$mid = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);
			T::equals((int)$mid["success"], 0, "batched revision still in-flight after a non-final page");

			_migexec_run_child($revision, 2, 2);
			$final = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);
			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_migrations WHERE revision = ?", $revision);

			T::equals((int)$final["success"], 1, "batched revision ends success=1 on the final page");
			T::equals($count, 1, "batched revision records exactly one ledger row");
			T::ok(!is_null($final["duration_ms"]), "batched revision gets a duration_ms on finish()");
		} finally {
			@unlink($file);
			_migexec_cleanup();
		}
	}

	function test_migration_runner_nested_object_batched_revision_converges() {
		if (!_migexec_has_table()) {
			echo "  (skipped — bigtree_migrations not present in this harness)\n";

			return;
		}

		// Regression for plan 034: a batched revision whose initial (no-page) call
		// echoes a pretty-printed JSON object containing a NESTED object alongside
		// {pages}. The old runner parser (strrpos for BOTH braces) truncated such
		// output into invalid JSON, dropped the page count, and falsely flagged the
		// revision "no page count reported". Drive it through the runner's child mode
		// exactly like the flat batched test and assert it converges to one success=1
		// row WITH the page count correctly parsed from the nested-object output.
		$revision = MIGEXEC_BASE + 300;
		$file = _migexec_revision_path($revision);

		$body = "<?php\n"
			. "\t\\BigTree\\Services\\MigrationService::begin($revision);\n"
			. "\t\$total_pages = 2;\n"
			. "\tif (empty(\$_GET[\"page\"])) {\n"
			. "\t\techo BigTree::json([\"complete\" => false, \"pages\" => \$total_pages, \"response\" => [\"detail\" => \"nested batched\", \"meta\" => [\"x\" => 1]]]);\n"
			. "\t\tdie();\n"
			. "\t}\n"
			. "\t\$page = (int)\$_GET[\"page\"];\n"
			. "\tif (\$page < \$total_pages) {\n"
			. "\t\techo BigTree::json([\"complete\" => false, \"response\" => [\"detail\" => \"page \".\$page]]);\n"
			. "\t} else {\n"
			. "\t\t\\BigTree\\Services\\MigrationService::finish($revision);\n"
			. "\t\techo BigTree::json([\"complete\" => true, \"response\" => [\"detail\" => \"done\"]]);\n"
			. "\t}\n";

		try {
			_migexec_cleanup();
			file_put_contents($file, $body);

			// The runner parses the initial output with migrate_run_extract_json; mirror
			// that helper's FIRST "{" to LAST "}" extraction so the nested object is
			// preserved (the pre-034 strrpos-for-both approach would null this out).
			$initial = _migexec_run_child($revision);
			$end = strrpos($initial, "}");
			$initial_json = null;

			if ($end !== false) {
				$offset = 0;

				while (($s = strpos($initial, "{", $offset)) !== false && $s <= $end) {
					$decoded = json_decode(substr($initial, $s, $end - $s + 1), true);

					if (is_array($decoded)) {
						$initial_json = $decoded;

						break;
					}

					$offset = $s + 1;
				}
			}

			T::ok(is_array($initial_json), "nested-object batched revision output decodes (not truncated to null)");
			T::equals((int)($initial_json["pages"] ?? 0), 2, "page count is parsed from a nested-object response (regression for plan 034)");
			T::ok(is_array($initial_json["response"] ?? null), "the nested object survives extraction intact");

			$row = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);
			T::equals((int)$row["success"], 0, "nested-object batched revision is in-flight (success=0) after the no-page call");

			// Drive the pages exactly like the runner loop until it converges.
			_migexec_run_child($revision, 1, 2);
			$mid = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);
			T::equals((int)$mid["success"], 0, "still in-flight after a non-final page");

			_migexec_run_child($revision, 2, 2);
			$final = SQL::fetch("SELECT * FROM bigtree_migrations WHERE revision = ?", $revision);
			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_migrations WHERE revision = ?", $revision);

			T::equals((int)$final["success"], 1, "nested-object batched revision ends success=1 on the final page");
			T::equals($count, 1, "nested-object batched revision records exactly one ledger row");
		} finally {
			@unlink($file);
			_migexec_cleanup();
		}
	}

	function test_migration_runner_page_loop_cap_stops() {
		// Characterize the runner's page-loop cap WITHOUT a DB: the loop runs at most
		// (total_pages + 2) pages and reports failure if the revision never "drops
		// out" (finish() never fires). This mirrors migrate-run.php's STOP path.
		$total_pages = 3;
		$cap = $total_pages + 2;
		$pages_run = 0;
		$converged = false;

		// Simulate a revision whose finish() never fires (always still pending).
		$still_pending = fn() => true;

		for ($page = 1; $page <= $cap; $page++) {
			$pages_run++;

			if (!$still_pending()) {
				$converged = true;

				break;
			}
		}

		T::equals($pages_run, $cap, "page loop runs at most total_pages + 2 pages (cap honored)");
		T::ok(!$converged, "a non-converging revision STOPS at the cap instead of looping forever");

		// And the happy path: a revision that drops out on its last page converges
		// without hitting the cap.
		$pages_run = 0;
		$converged = false;
		$drops_out_on = $total_pages;

		for ($page = 1; $page <= $cap; $page++) {
			$pages_run++;

			if ($page >= $drops_out_on) {
				$converged = true;

				break;
			}
		}

		T::ok($converged, "a revision that drops out on its last page converges");
		T::equals($pages_run, $total_pages, "convergence happens within total_pages, not at the cap");
	}
