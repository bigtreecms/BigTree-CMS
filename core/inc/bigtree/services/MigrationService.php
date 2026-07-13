<?php
	namespace BigTree\Services;

	use SQL;
	use BigTreeCMS;

	/**
	 * Migration ledger (017 Phase 1 + 2a).
	 *
	 * Records completed schema migrations alongside the legacy
	 * `bigtree-internal-revision` integer, and (Phase 2a) provides the READ-side
	 * analysis used by the status CLI: `checksumFor()`, `pending()`, and
	 * `verifyIntegrity()`. Phase 2a is strictly read-only with respect to migration
	 * EXECUTION — there is deliberately no `apply()`/runner here; that, the AJAX
	 * upgrade-controller routing, and batched execution are Phase 2b (plan 032).
	 */
	class MigrationService {
		/** Path (relative to SERVER_ROOT) of the upgrade revisions directory. */
		const REVISIONS_DIR = "core/admin/ajax/developer/upgrade/revisions/";

		/** Sentinel checksum used by the 505 backfill for pre-ledger revisions. */
		const PRE_LEDGER = "pre-ledger";
		/**
		 * Idempotently record a revision as a completed migration (success = 1).
		 *
		 * Safe to call repeatedly for the same revision — the primary key on
		 * `revision` plus ON DUPLICATE KEY UPDATE refreshes the metadata in place
		 * instead of inserting a duplicate row.
		 *
		 * @param int         $revision Revision number (the N in revisions/N.php).
		 * @param string      $name     Human-readable migration name.
		 * @param string|null $checksum Optional checksum; null is stored as "".
		 */
		public static function record(int $revision, string $name = "", ?string $checksum = null): void {
			SQL::query(
				"INSERT INTO bigtree_migrations (revision, name, checksum, applied_at, success)
					VALUES (?, ?, ?, NOW(), 1)
					ON DUPLICATE KEY UPDATE
						name = VALUES(name),
						checksum = VALUES(checksum),
						applied_at = NOW(),
						success = 1",
				$revision,
				$name,
				$checksum ?? ""
			);
		}

		/**
		 * Mark a revision as STARTED (the "in-flight" marker) — 017 Phase 2b.
		 *
		 * Idempotent run-path entry point, called at the top of a new revision (and
		 * safe to call on every page of a batched one):
		 *
		 *   - No ledger row for N  -> insert success=0, applied_at=NOW(), and the
		 *     checksum ($checksum ?? checksumFor(N)). This is the resume signal: a
		 *     crash/timeout between begin() and finish() leaves a success=0 row.
		 *   - A success=0 row already exists (resumed/batched run) -> leave it as is,
		 *     so applied_at keeps measuring from the original start.
		 *   - A success=1 row already exists (already applied) -> no-op.
		 *
		 * @param int         $revision Revision number (the N in revisions/N.php).
		 * @param string|null $checksum Optional checksum; defaults to checksumFor(N).
		 */
		public static function begin(int $revision, ?string $checksum = null): void {
			$existing = SQL::fetch("SELECT success FROM bigtree_migrations WHERE revision = ?", $revision);
			$checksum = $checksum ?? self::checksumFor($revision);
			$floor = (int)BigTreeCMS::getSetting("bigtree-internal-revision");

			if ($existing) {
				// Allow a true re-run after someone rewound bigtree-internal-revision
				// below this N (e.g. testing) — reset the in-flight marker so finish()
				// can complete again. Do not touch rows still ahead of the floor.
				if ((int)$existing["success"] === 1 && $floor < $revision) {
					SQL::query(
						"UPDATE bigtree_migrations
							SET success = 0, checksum = ?, applied_at = NOW(), duration_ms = NULL
							WHERE revision = ?",
						$checksum,
						$revision
					);
				}

				return;
			}

			SQL::query(
				"INSERT INTO bigtree_migrations (revision, name, checksum, applied_at, success)
					VALUES (?, ?, ?, NOW(), 0)",
				$revision,
				"",
				$checksum
			);
		}

		/**
		 * Mark a revision as COMPLETED and advance the legacy integer — 017 Phase 2b.
		 *
		 * Called once a revision finishes (single-shot: at the end; batched: only on
		 * the {complete:true} branch). Idempotent:
		 *
		 *   - Flips the row to success=1 and sets duration_ms = NOW() - applied_at.
		 *   - Advances bigtree-internal-revision to **this** revision only when it is
		 *     higher than the current floor. We deliberately do NOT jump to MAX(ledger)
		 *     — stale success=1 rows from a previous run (after a manual revision rewind)
		 *     would otherwise skip intermediate scripts and break the SPA runner with
		 *     "Unknown or out-of-order migration script".
		 *
		 * @param int $revision Revision number (the N in revisions/N.php).
		 */
		public static function finish(int $revision): void {
			SQL::query(
				"UPDATE bigtree_migrations
					SET success = 1,
						duration_ms = GREATEST(0, TIMESTAMPDIFF(MICROSECOND, applied_at, NOW()) DIV 1000)
					WHERE revision = ?",
				$revision
			);

			self::advanceInternalRevisionTo($revision);
		}

		/**
		 * Advance bigtree-internal-revision to $revision if higher (never lower).
		 */
		private static function advanceInternalRevisionTo(int $revision): void {
			$current = (int)BigTreeCMS::getSetting("bigtree-internal-revision");

			if ($revision <= $current) {
				return;
			}

			if ($revision > BIGTREE_REVISION) {
				$revision = BIGTREE_REVISION;
			}

			SettingService::updateInternalValue("bigtree-internal-revision", $revision);
		}

		/**
		 * Mirror the legacy integer to the highest *contiguous* applied revision
		 * (never lower). Used by tooling that needs a full resync — not by finish(),
		 * which advances one step at a time.
		 *
		 * Contiguous = every on-disk revisions/M.php with M <= N is success=1 (or
		 * M is at/below the previous floor). Prevents jumping over gaps.
		 */
		public static function mirrorInternalRevision(): void {
			$floor = (int)BigTreeCMS::getSetting("bigtree-internal-revision");
			$applied = self::successfulLedgerRevisions();
			$target = BIGTREE_REVISION;
			$contiguous = $floor;

			foreach (self::onDiskRevisions() as $n) {
				if ($n > $target) {
					break;
				}

				if ($n <= $floor) {
					$contiguous = max($contiguous, $n);

					continue;
				}

				if (!isset($applied[$n])) {
					break;
				}

				$contiguous = $n;
			}

			if ($contiguous > $floor) {
				SettingService::updateInternalValue("bigtree-internal-revision", $contiguous);
			}
		}

		/**
		 * SHA-256 of a revision file's contents.
		 *
		 * Resolves the path exactly like 505.php's backfill glob does — under
		 * SERVER_ROOT, so it works identically in the web upgrade context and the
		 * headless CLI. Returns "" if the revision file does not exist.
		 *
		 * @param int $revision Revision number (the N in revisions/N.php).
		 *
		 * @return string A 64-character lowercase hex digest, or "" if missing.
		 */
		public static function checksumFor(int $revision): string {
			$file = SERVER_ROOT . self::REVISIONS_DIR . $revision . ".php";

			if (!is_file($file)) {
				return "";
			}

			$hash = hash_file("sha256", $file);

			return $hash === false ? "" : $hash;
		}

		/**
		 * On-disk revision numbers (revisions/N.php) in ascending order.
		 *
		 * @return int[] Ascending list of revision numbers found on disk.
		 */
		public static function onDiskRevisions(): array {
			$revisions = [];

			foreach (glob(SERVER_ROOT . self::REVISIONS_DIR . "*.php") as $file) {
				$n = (int)basename($file, ".php");

				if ($n > 0) {
					$revisions[] = $n;
				}
			}

			sort($revisions);

			return $revisions;
		}

		/**
		 * Revisions that WOULD run, in ascending order.
		 *
		 * A revision N (an on-disk revisions/N.php with N <= BIGTREE_REVISION) is
		 * PENDING unless it is already applied, where:
		 *
		 *     applied = (N <= (int)bigtree-internal-revision)
		 *               OR (a success=1 ledger row exists for N)
		 *
		 * The `<= integer` clause is the FLOOR that keeps a fresh install (empty
		 * ledger, integer at the target) correctly reporting nothing pending. This
		 * floor semantics MUST be reused by the Phase 2b runner.
		 *
		 * Read-only — this method never executes a revision.
		 *
		 * @return int[] Ascending list of pending revision numbers.
		 */
		public static function pending(): array {
			$target = BIGTREE_REVISION;
			$floor = (int)BigTreeCMS::getSetting("bigtree-internal-revision");
			$applied = self::successfulLedgerRevisions();
			$pending = [];

			foreach (self::onDiskRevisions() as $n) {
				if ($n > $target) {
					continue;
				}

				if ($n <= $floor || isset($applied[$n])) {
					continue;
				}

				$pending[] = $n;
			}

			return $pending;
		}

		/**
		 * Detect drift between recorded checksums and the on-disk revision files.
		 *
		 * For each success=1 ledger row whose stored checksum is a REAL one (not ""
		 * and not the "pre-ledger" sentinel — those are backfilled/unverifiable),
		 * recompute checksumFor() and, on mismatch or a missing file, append:
		 *
		 *     ["revision" => N, "stored" => ..., "actual" => ..., "status" => "drift"|"missing"]
		 *
		 * @return array Drift records; [] when every verifiable revision matches.
		 */
		public static function verifyIntegrity(): array {
			$rows = SQL::fetchAll(
				"SELECT revision, checksum FROM bigtree_migrations WHERE success = 1 ORDER BY revision ASC"
			);
			$drift = [];

			foreach ($rows as $row) {
				$stored = (string)$row["checksum"];

				if ($stored === "" || $stored === self::PRE_LEDGER) {
					continue;
				}

				$revision = (int)$row["revision"];
				$actual = self::checksumFor($revision);

				if ($actual === "") {
					$drift[] = [
						"revision" => $revision,
						"stored" => $stored,
						"actual" => $actual,
						"status" => "missing"
					];

					continue;
				}

				if (!hash_equals($stored, $actual)) {
					$drift[] = [
						"revision" => $revision,
						"stored" => $stored,
						"actual" => $actual,
						"status" => "drift"
					];
				}
			}

			return $drift;
		}

		/**
		 * Map of revision => true for every success=1 ledger row.
		 *
		 * @return array<int,bool> Keyed by revision number.
		 */
		private static function successfulLedgerRevisions(): array {
			$applied = [];

			foreach (SQL::fetchAllSingle("SELECT revision FROM bigtree_migrations WHERE success = 1") as $revision) {
				$applied[(int)$revision] = true;
			}

			return $applied;
		}
	}
