<?php
	/**
	 * SearchService relevance ranking (no live AI / DB required for score helpers).
	 */

	function test_search_relevance_prefers_phrase_and_synonym_match() {
		$svc = (new ReflectionClass(\BigTree\Services\SearchService::class))
			->newInstanceWithoutConstructor();
		$score = new ReflectionMethod(\BigTree\Services\SearchService::class, "relevanceScore");
		$score->setAccessible(true);

		$q = "die by trees";
		$keywords = ["die", "trees"];

		$uber = (float)$score->invoke(
			$svc,
			$q,
			$keywords,
			"Uber for Trees: Timber brings lumberjacks to your door",
			null
		);
		$killed = (float)$score->invoke(
			$svc,
			$q,
			$keywords,
			"You Won't Believe How Many People Are Killed by Trees Every Year",
			null
		);

		T::ok($killed > $uber, "killed-by-trees outranks uber-for-trees for 'die by trees' (got killed=$killed uber=$uber)");
		T::ok($killed > 80, "strong lexical match scores high");
	}

	function test_search_relevance_uses_semantic_distance() {
		$svc = (new ReflectionClass(\BigTree\Services\SearchService::class))
			->newInstanceWithoutConstructor();
		$score = new ReflectionMethod(\BigTree\Services\SearchService::class, "relevanceScore");
		$score->setAccessible(true);

		$q = "trees";
		$keywords = ["trees"];

		// Same title text; closer vector wins.
		$near = (float)$score->invoke($svc, $q, $keywords, "About trees", 0.1);
		$far = (float)$score->invoke($svc, $q, $keywords, "About trees", 0.9);

		T::ok($near > $far, "closer cosine distance scores higher (near=$near far=$far)");
	}

	function test_search_rank_entry_groups_orders_items() {
		$svc = (new ReflectionClass(\BigTree\Services\SearchService::class))
			->newInstanceWithoutConstructor();
		$rank = new ReflectionMethod(\BigTree\Services\SearchService::class, "rankCollectedResults");
		$rank->setAccessible(true);
		$present = new ReflectionMethod(\BigTree\Services\SearchService::class, "presentCollectedResults");
		$present->setAccessible(true);

		$collected = [
			"pages" => [],
			"modules" => [],
			"entries" => [[
				"module" => ["id" => "news", "name" => "News", "route" => "news"],
				"items" => [
					[
						"id" => 1,
						"column1" => "Uber for Trees: Timber brings lumberjacks to your door",
						"column2" => "Uber for Trees: Timber brings lumberjacks to your door",
					],
					[
						"id" => 2,
						"column1" => "You Won't Believe How Many People Are Killed by Trees Every Year",
						"column2" => "You Won't Believe How Many People Are Killed by Trees Every Year",
					],
				],
			]],
			"tags" => [],
			"users" => [],
		];

		$ranked = $rank->invoke($svc, $collected, "die by trees", ["die", "trees"]);
		$wire = $present->invoke($svc, $ranked);

		$items = $wire["entries"][0]["items"] ?? [];
		T::ok(count($items) === 2, "still two entry items");
		T::equals((string)($items[0]["id"] ?? ""), "2", "killed-by-trees is first after ranking");
		T::ok(!isset($items[0]["_score"]), "internal _score stripped for the wire");
		T::ok(!isset($items[0]["_distance"]), "internal _distance stripped for the wire");
	}
