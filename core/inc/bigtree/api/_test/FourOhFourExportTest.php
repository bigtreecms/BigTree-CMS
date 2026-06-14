<?php
	/**
	 * Bounded, chunked export in FourOhFourService::export().
	 *
	 * bigtree_404s grows unbounded on a busy site; export() used to SELECT every
	 * matching row into one array, risking memory_limit exhaustion on an "Export
	 * CSV" click. It now fetches in chunks under a hard cap and returns capped/max
	 * meta so the SPA can warn on truncation.
	 *
	 * These tests seed a small batch under a unique site_key (so they are isolated
	 * from any real rows in the connected DB), call export() with a fake Request,
	 * and assert: all seeded rows come back, ordered by requests DESC, with
	 * capped=false and max present. The 50000/1000 cap/chunk constants are the
	 * safety bound — exercising the multi-chunk path would require seeding >1000
	 * rows, which is deliberately not done here to keep the suite fast.
	 *
	 * Skips on an unavailable DB.
	 */

	use BigTree\Services\FourOhFourService;
	use BigTree\Api\Request;

	function _export_request(string $type, ?string $site_key): Request {
		$req = new Request();
		$req->query = ["type" => $type];

		if ($site_key !== null) {
			$req->query["site_key"] = $site_key;
		}

		return $req;
	}

	function test_404_export_returns_all_rows_ordered_with_meta() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_404s LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		$site_key = "ZZ_export_" . uniqid();
		$ids = [];

		try {
			// 12 type=404 rows (redirect_url='' and ignored='') with ascending
			// request counts 1..12, inserted out of order to prove ORDER BY.
			$counts = [3, 11, 1, 7, 12, 5, 9, 2, 10, 4, 8, 6];

			foreach ($counts as $i => $requests) {
				$ids[] = (int)SQL::insert("bigtree_404s", [
					"broken_url" => "/broken-" . $i,
					"get_vars" => "",
					"redirect_url" => "",
					"requests" => $requests,
					"ignored" => "",
					"site_key" => $site_key,
				]);
			}

			$svc = new FourOhFourService();
			$resp = $svc->export(_export_request("404", $site_key));

			$data = $resp->body["data"];
			$meta = $resp->body["meta"] ?? null;

			T::equals(count($data), 12, "export returns all 12 seeded rows");

			// Ordered by requests DESC → 12, 11, 10, ... 1.
			$got = array_map(fn ($r) => $r["requests"], $data);
			$expected = range(12, 1);
			T::equals($got, $expected, "rows ordered by requests DESC");

			// present() shape carried through.
			T::equals($data[0]["type"], "404", "top row presented as type 404");
			T::equals($data[0]["site_key"], $site_key, "site_key carried through present()");

			// Meta surfaces the cap so the SPA can warn on truncation.
			T::ok(is_array($meta), "response carries meta");
			T::equals($meta["max"], 50000, "meta exposes the max cap");
			T::equals($meta["capped"], false, "small export is not capped");
		} finally {
			foreach ($ids as $id) {
				SQL::query("DELETE FROM bigtree_404s WHERE id = ?", $id);
			}
		}
	}

	function test_404_export_empty_bucket_is_empty_uncapped() {
		try {
			SQL::fetchSingle("SELECT id FROM bigtree_404s LIMIT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return;
		}

		// A site_key that matches nothing → empty data, still uncapped with meta.
		$svc = new FourOhFourService();
		$resp = $svc->export(_export_request("404", "ZZ_export_none_" . uniqid()));

		T::equals(count($resp->body["data"]), 0, "no matching rows → empty data");
		T::equals($resp->body["meta"]["capped"], false, "empty export is not capped");
	}
