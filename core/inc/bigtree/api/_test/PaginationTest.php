<?php
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Exceptions\BadRequestException;

	/** True when the bigtree_tags table is reachable in this harness. */
	function _pagination_db_ready(): bool {
		try {
			SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — bigtree_tags unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	function test_pagination_paginate_runs_count_slice_and_meta() {
		if (!_pagination_db_ready()) {
			return;
		}

		$prefix = "zzpag_" . substr(uniqid(), -8) . "_";
		$ids = [];

		try {
			// Seed five rows that share a searchable prefix.
			for ($i = 1; $i <= 5; $i++) {
				$tag = $prefix . $i;
				$ids[] = (int)SQL::insert("bigtree_tags", [
					"tag" => $tag,
					"metaphone" => metaphone($tag),
					"route" => $tag,
					"usage_count" => 0,
				]);
			}

			$where = " WHERE tag LIKE ?";
			$args = [$prefix . "%"];

			// Page 1 of 2 (per_page = 2): expect the first two rows by tag ASC and
			// meta reflecting the full filtered total.
			$req = new Request();
			$req->query = ["page" => 1, "per_page" => 2];

			$response = Pagination::paginate(
				$req,
				"SELECT COUNT(*) FROM bigtree_tags" . $where,
				"SELECT tag FROM bigtree_tags" . $where . " ORDER BY tag ASC",
				$args,
				function ($r) {

					return $r["tag"];
				},
				100
			);

			T::equals($response->status, 200, "paginate returns a 200 response");
			T::equals(count($response->body["data"]), 2, "page slice honors per_page");
			T::equals($response->body["data"][0], $prefix . "1", "rows mapped through present in order");
			T::equals($response->body["meta"]["total"], 5, "meta total counts the full filtered set");
			T::equals($response->body["meta"]["pages"], 3, "meta pages = ceil(total / per_page)");

			// Last page returns the remainder.
			$req3 = new Request();
			$req3->query = ["page" => 3, "per_page" => 2];

			$response3 = Pagination::paginate(
				$req3,
				"SELECT COUNT(*) FROM bigtree_tags" . $where,
				"SELECT tag FROM bigtree_tags" . $where . " ORDER BY tag ASC",
				$args,
				function ($r) {

					return $r["tag"];
				},
				100
			);

			T::equals(count($response3->body["data"]), 1, "final page returns the remainder");
			T::equals($response3->body["data"][0], $prefix . "5", "final page slice offset is correct");
		} finally {
			foreach ($ids as $id) {
				SQL::delete("bigtree_tags", $id);
			}
		}
	}

	function test_cursor_roundtrip() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$payload = ["id" => 12345];
		$cursor = Pagination::encodeCursor($payload, $secret);
		$decoded = Pagination::decodeCursor($cursor, $secret);
		T::equals($decoded["id"], 12345, "cursor round-trip preserves payload");
	}

	function test_cursor_tamper_detected() {
		$secret = "test-secret-at-least-32-bytes-long-string";
		$cursor = Pagination::encodeCursor(["id" => 12345], $secret);
		// Tamper with the body.
		[$body, $sig] = explode(".", $cursor, 2);
		$tampered = rtrim(strtr(base64_encode(json_encode(["id" => 99999])), "+/", "-_"), "=") . "." . $sig;
		T::throws(function () use ($tampered, $secret) {
			Pagination::decodeCursor($tampered, $secret);
		}, BadRequestException::class, "tampered cursor rejected");
	}

	function test_cursor_different_secret() {
		$cursor = Pagination::encodeCursor(["id" => 1], "secret-A-at-least-32-bytes-long-string");
		T::throws(function () use ($cursor) {
			Pagination::decodeCursor($cursor, "secret-B-at-least-32-bytes-long-string");
		}, BadRequestException::class, "cursor signed with different secret rejected");
	}
