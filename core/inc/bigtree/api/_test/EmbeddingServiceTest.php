<?php
	/**
	 * EmbeddingService pure helpers + BigTreeAI::parseVectorSupport.
	 * No DB or provider calls — exercised via reflection like SearchServiceRankingTest.
	 */

	function embedding_service_method($name) {
		$m = new ReflectionMethod(\BigTree\Services\EmbeddingService::class, $name);
		$m->setAccessible(true);

		return $m;
	}

	function test_embedding_chunk_text_empty_and_short() {
		$chunk = embedding_service_method("chunkText");

		T::equals($chunk->invoke(null, ""), [], "empty string yields no chunks");
		T::equals($chunk->invoke(null, "   "), [], "whitespace-only yields no chunks");
		T::equals($chunk->invoke(null, "hello world"), ["hello world"], "short text is a single chunk");
		T::equals($chunk->invoke(null, "a   b\n\tc"), ["a b c"], "whitespace is collapsed");
	}

	function test_embedding_chunk_text_overlap_and_cap() {
		$chunk = embedding_service_method("chunkText");

		// 10000 chars (> 6000 soft max) → 4000-char chunks stepping by 3800.
		$long = str_repeat("a", 10000);
		$chunks = $chunk->invoke(null, $long);
		T::equals(count($chunks), 3, "10000 chars splits into 3 chunks");
		T::equals(mb_strlen($chunks[0]), 4000, "first chunk is CHUNK_SIZE");
		T::equals(mb_strlen($chunks[1]), 4000, "second chunk is CHUNK_SIZE");
		T::equals(mb_strlen($chunks[2]), 2400, "final chunk holds the remainder");

		// Overlap: chunk N starts 200 chars before chunk N-1's end (step 3800).
		$marked = str_repeat("x", 3800) . "SEAM" . str_repeat("y", 10000);
		$chunks = $chunk->invoke(null, $marked);
		T::ok(strpos($chunks[1], "SEAM") === 0 || strpos($chunks[1], "xSEAM") !== false, "overlap keeps the seam region in the next chunk");

		// 20-chunk cap holds for very long input.
		$huge = str_repeat("z", 200000);
		T::equals(count($chunk->invoke(null, $huge)), 20, "chunk count is capped at 20");
	}

	function test_embedding_normalize_dimensions() {
		$norm = embedding_service_method("normalizeDimensions");
		$n = (int)\BigTreeAI::EMBEDDING_DIMENSIONS;

		$exact = array_fill(0, $n, 0.5);
		T::equals($norm->invoke(null, $exact), $exact, "exact-length vector is unchanged");

		$over = array_fill(0, $n + 500, 1.0);
		$result = $norm->invoke(null, $over);
		T::equals(count($result), $n, "over-length vector is truncated to dimensions");

		$under = [1.5, -2.25];
		$result = $norm->invoke(null, $under);
		T::equals(count($result), $n, "under-length vector is padded to dimensions");
		T::equals($result[0], 1.5, "padding preserves leading values");
		T::equals($result[1], -2.25, "padding preserves negative leading values");
		T::equals($result[$n - 1], 0.0, "padding fills with 0.0");
	}

	function test_embedding_vector_to_literal() {
		$lit = embedding_service_method("vectorToLiteral");

		T::equals($lit->invoke(null, [1.0, 2.5, 0.0]), "[1,2.5,0]", "trailing zeros and decimal point trimmed");
		T::equals($lit->invoke(null, [-0.125]), "[-0.125]", "negative float renders correctly");
		T::equals($lit->invoke(null, [1.10000000]), "[1.1]", "trailing zeros trimmed to shortest form");
		T::equals($lit->invoke(null, []), "[]", "empty vector renders as empty literal");
	}

	function test_embedding_resolve_reindex_page() {
		$resolve = embedding_service_method("resolveReindexPage");
		$segments = [
			["type" => "pages", "label" => "pages", "count" => 5],
			["type" => "settings", "label" => "settings", "count" => 1],
		];

		// batch_size 2 → pages span pages 1-3 (ceil(5/2)), settings is page 4.
		$p1 = $resolve->invoke(null, 1, $segments, 2);
		T::equals($p1["segment"]["type"], "pages", "page 1 lands in the pages segment");
		T::equals($p1["batch"], 1, "page 1 is batch 1 of pages");

		$p3 = $resolve->invoke(null, 3, $segments, 2);
		T::equals($p3["segment"]["type"], "pages", "page 3 still in pages");
		T::equals($p3["batch"], 3, "page 3 is batch 3 of pages");

		$p4 = $resolve->invoke(null, 4, $segments, 2);
		T::equals($p4["segment"]["type"], "settings", "page 4 crosses into settings");
		T::equals($p4["batch"], 1, "page 4 is batch 1 of settings");

		T::equals($resolve->invoke(null, 5, $segments, 2), null, "page past the last segment resolves to null");

		// Zero-count segments are skipped.
		$skip = [
			["type" => "pages", "label" => "pages", "count" => 0],
			["type" => "settings", "label" => "settings", "count" => 3],
		];
		$first = $resolve->invoke(null, 1, $skip, 2);
		T::equals($first["segment"]["type"], "settings", "empty pages segment is skipped");
		T::equals($first["batch"], 1, "first real page is batch 1 of the next non-empty segment");
	}

	function test_bigtree_ai_parse_vector_support() {
		T::ok(\BigTreeAI::parseVectorSupport("11.7.1-MariaDB"), "MariaDB 11.7 supports VECTOR");
		T::ok(\BigTreeAI::parseVectorSupport("11.8.0-MariaDB"), "MariaDB 11.8 supports VECTOR");
		T::ok(\BigTreeAI::parseVectorSupport("12.0.1-MariaDB"), "MariaDB 12 supports VECTOR");
		T::ok(!\BigTreeAI::parseVectorSupport("11.6.2-MariaDB"), "MariaDB 11.6 does not");
		T::ok(!\BigTreeAI::parseVectorSupport("10.11.6-MariaDB"), "MariaDB 10.11 does not");
		T::ok(\BigTreeAI::parseVectorSupport("9.0.1"), "MySQL 9 supports VECTOR");
		T::ok(!\BigTreeAI::parseVectorSupport("8.0.36"), "MySQL 8 does not");
		T::ok(!\BigTreeAI::parseVectorSupport(""), "empty version string does not");
		T::ok(!\BigTreeAI::parseVectorSupport("not-a-version"), "unparseable version does not");
	}
