<?php
	/**
	 * Headless migration RUNNER (017 Phase 2b, plan 032).
	 *
	 * Applies every pending revision (MigrationService::pending(), ascending) from
	 * the command line — the CI/CD + long-backfill counterpart to the browser AJAX
	 * upgrade flow. It does NOT touch the AJAX path: it simply drives the same
	 * revisions/N.php files, and the begin()/finish() recording in each new revision
	 * (or applied via this runner) keeps the ledger + the legacy integer consistent
	 * regardless of which path runs them.
	 *
	 * Each revisions/N.php echoes JSON {complete, response, error, pages}:
	 *   - single-shot: one call echoes {complete:true};
	 *   - batched (e.g. 503): the first call (no page) echoes {pages:X}, then one
	 *     page per call (page=1..X) until the last echoes {complete:true}.
	 *
	 * Because some revisions call die()/exit() and write their own output, each page
	 * is run in an ISOLATED child process — this very script re-invoked with the
	 * internal --exec-revision flag — so a die() cannot kill the runner and the JSON
	 * can be captured cleanly. The child shares the same DB, so begin()/finish()
	 * persist; the parent confirms completion by re-checking pending() (the revision
	 * dropping out == finish() ran), falling back to the captured {pages}/{error}
	 * for batching + failure detection.
	 *
	 * Usage:
	 *   php core/admin/migrate-run.php            apply all pending revisions
	 *   php core/admin/migrate-run.php --dry-run  report status only (runs nothing)
	 *
	 * Exit code: non-zero on any failure (a revision left success=0, echoed
	 * {error}, or a batched revision that did not converge within its page cap).
	 */

	use BigTree\Services\MigrationService;

	/**
	 * Extract a revision's JSON object from captured child output.
	 *
	 * BigTree::json() pretty-prints (multi-line), and a revision may emit leading
	 * notices, so we take the substring from the FIRST "{" to the LAST "}" and decode
	 * it. Returns the decoded array, or null if no JSON object is present.
	 */
	function migrate_run_extract_json(string $raw): ?array {
		$end = strrpos($raw, "}");

		if ($end === false) {

			return null;
		}

		// Scan opening braces left-to-right: the outermost object starts at the
		// FIRST "{" before the last "}". Retry from later "{" positions so leading
		// process output containing a stray brace (a PHP notice, etc.) can't defeat
		// the decode. The previous strrpos()-for-both approach truncated any object
		// with a nested object into invalid JSON.
		$offset = 0;

		while (($start = strpos($raw, "{", $offset)) !== false && $start <= $end) {
			$decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

			if (is_array($decoded)) {

				return $decoded;
			}

			$offset = $start + 1;
		}

		return null;
	}

	// Mirror migrate-status.php's bootstrap so SQL / BigTreeCMS / constants load.
	$server_root = str_replace("core/admin/migrate-run.php", "", strtr(__FILE__, "\\", "/"));

	include $server_root . "custom/environment.php";
	include $server_root . "custom/settings.php";
	include $server_root . "core/bootstrap.php";

	$args = array_slice($argv, 1);

	// --------------------------------------------------------------------------
	// INTERNAL child mode: --exec-revision N [page total_pages]
	//
	// Replicates the request context revisions read (503 reads $_GET["page"] and
	// $_GET["total_pages"]) and includes revisions/N.php, then exits. Isolating
	// this in a child process means a revision's die()/exit() ends only the child,
	// and its JSON is the child's stdout. Not for direct human use.
	// --------------------------------------------------------------------------
	if (($args[0] ?? "") === "--exec-revision") {
		$revision = (int)($args[1] ?? 0);
		$file = $server_root . MigrationService::REVISIONS_DIR . $revision . ".php";

		if (!is_file($file)) {
			echo BigTree::json(["error" => "revision file not found: " . $revision]);

			exit(0);
		}

		// Context a revision expects: $cms (bootstrap sets it), $admin, SERVER_ROOT,
		// plus the page params from the AJAX protocol when batching.
		if (!isset($cms)) {
			$cms = new BigTreeCMS;
		}

		// The admin constructor checks $bigtree["php_boot_error"] (set by the web
		// front controller, absent in CLI); default it so the child stays quiet.
		if (!isset($bigtree["php_boot_error"])) {
			$bigtree["php_boot_error"] = null;
		}

		$admin = new BigTreeAdmin;

		if (isset($args[2])) {
			$_GET["page"] = (int)$args[2];
			$_GET["total_pages"] = (int)($args[3] ?? 0);
			$_REQUEST["page"] = $_GET["page"];
			$_REQUEST["total_pages"] = $_GET["total_pages"];
		}

		include $file;

		exit(0);
	}

	// --------------------------------------------------------------------------
	// MAIN runner mode.
	// --------------------------------------------------------------------------
	$dry_run = in_array("--dry-run", $args, true);

	echo "================================================================\n";
	echo " BigTree migration runner" . ($dry_run ? " (DRY RUN — nothing will be applied)" : "") . "\n";
	echo "================================================================\n";
	echo "\n";

	// The ledger arrives with revision 505. If the table is absent the DB predates
	// it — there is nothing this runner can do; say so and exit cleanly.
	try {
		SQL::query("SELECT 1 FROM bigtree_migrations LIMIT 1");
	} catch (\Throwable $e) {
		echo "Migration ledger (bigtree_migrations) not found — run the upgrade to\n";
		echo "revision 505 to create it. Nothing to run.\n";

		exit(0);
	}

	$current = (int)BigTreeCMS::getSetting("bigtree-internal-revision");
	$target = BIGTREE_REVISION;

	echo "Applied revision (bigtree-internal-revision): " . $current . "\n";
	echo "Target revision  (BIGTREE_REVISION):          " . $target . "\n";
	echo "\n";

	$pending = MigrationService::pending();

	if (!$pending) {
		echo "Pending revisions (would run): none — up to date\n";

		exit(0);
	}

	echo "Pending revisions (would run): " . implode(", ", $pending) . "\n";
	echo "\n";

	if ($dry_run) {
		echo "Dry run — not applying. Re-run without --dry-run to apply.\n";
		echo "(For full status incl. checksum drift: php core/admin/migrate-status.php)\n";

		exit(0);
	}

	/**
	 * Run ONE page of a revision in an isolated child process (this script with
	 * --exec-revision). $page === null is the initial no-page call a batched
	 * revision uses to report its count. Returns ["json" => array|null, "raw" => string].
	 */
	$run_page = function (int $revision, ?int $page, ?int $total_pages) use ($server_root): array {
		$cmd = escapeshellarg(PHP_BINARY)
			. " " . escapeshellarg($server_root . "core/admin/migrate-run.php")
			. " --exec-revision " . escapeshellarg((string)$revision);

		if ($page !== null) {
			$cmd .= " " . escapeshellarg((string)$page) . " " . escapeshellarg((string)$total_pages);
		}

		$raw = shell_exec($cmd . " 2>&1");
		$raw = $raw === null ? "" : trim($raw);

		return ["json" => migrate_run_extract_json($raw), "raw" => $raw];
	};

	$failures = [];

	foreach ($pending as $revision) {
		echo "Revision " . $revision . ": ";

		// Initial call — single-shot revisions complete here; batched ones report
		// their page count via {pages}.
		$result = $run_page($revision, null, null);
		$json = $result["json"];

		if ($json && !empty($json["error"])) {
			echo "ERROR — " . $json["error"] . "\n";
			$failures[] = $revision;

			continue;
		}

		// Did this single include already finish it? (finish() drops it out.)
		if (!in_array($revision, MigrationService::pending(), true)) {
			echo "applied (single-shot)\n";

			continue;
		}

		// Still pending — must be batched and have reported a page count.
		$total_pages = (int)($json["pages"] ?? 0);

		if ($total_pages <= 0) {
			echo "ERROR — still pending after run but no page count reported";
			echo ($result["raw"] !== "" ? " (output: " . $result["raw"] . ")" : "") . "\n";
			$failures[] = $revision;

			continue;
		}

		echo "batched — " . $total_pages . " page(s)\n";

		// Loop pages, re-checking pending() each time. Cap at total_pages plus a
		// small safety margin so a non-converging revision STOPS rather than spins.
		$cap = $total_pages + 2;
		$converged = false;

		for ($page = 1; $page <= $cap; $page++) {
			$page_result = $run_page($revision, $page, $total_pages);
			$page_json = $page_result["json"];

			echo "  page " . $page . "/" . $total_pages . ": ";

			if ($page_json && !empty($page_json["error"])) {
				echo "ERROR — " . $page_json["error"] . "\n";

				break;
			}

			if (!in_array($revision, MigrationService::pending(), true)) {
				echo "complete\n";
				$converged = true;

				break;
			}

			echo "ok\n";
		}

		if (!$converged) {
			echo "  STOPPED — revision " . $revision . " did not converge within " . $cap . " page(s) (cap reached); ";
			echo "left success=0. Inspect the revision or run it via the admin UI.\n";
			$failures[] = $revision;

			// Do not apply later revisions on top of an incomplete one.
			break;
		}

		echo "Revision " . $revision . ": applied (batched)\n";
	}

	echo "\n";
	echo "----------------------------------------------------------------\n";

	if ($failures) {
		echo "FAILED revisions: " . implode(", ", $failures) . "\n";
		echo "Final applied revision: " . (int)BigTreeCMS::getSetting("bigtree-internal-revision") . " (target " . $target . ")\n";

		exit(1);
	}

	echo "All pending revisions applied successfully.\n";
	echo "Final applied revision: " . (int)BigTreeCMS::getSetting("bigtree-internal-revision") . " (target " . $target . ")\n";

	exit(0);
