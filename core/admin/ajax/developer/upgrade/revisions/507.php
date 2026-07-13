<?php
	// BigTree 5.0 — batched AI embeddings backfill (when provider is already configured)
	//
	// 506 creates bigtree_ai_embeddings. This revision fills it when an embeddings-
	// capable AI provider is already configured. Uses the same page protocol as 503:
	//   first call → { complete:false, pages:N }
	//   page=1..N  → work one batch; last page → { complete:true }
	//
	// Embeddings always use OpenAI (text-embedding-3-small). Chat may be xAI/Anthropic
	// with a separate embedding_api_key. If embeddings are not configured yet, finish
	// immediately and leave filling to Developer → Configure → AI → Rebuild index.

	use BigTree\Services\MigrationService;
	use BigTree\Services\EmbeddingService;
	use BigTree\Services\SettingService;

	MigrationService::begin(507);

	if (!EmbeddingService::tableReady()) {
		MigrationService::finish(507);

		echo BigTree::json([
			"complete" => true,
			"response" => "AI embeddings backfill skipped (table not ready) (507)",
		]);

		return;
	}

	$ai = new BigTreeAI();

	// Default embedding model when any chat service is already set up.
	// Do NOT force-enable features.embeddings — that requires a real OpenAI
	// embeddings key (chat key only counts when service is openai).
	if ($ai->isConfigured()) {
		$settings = is_array($ai->Settings) ? $ai->Settings : [];
		$changed = false;

		if (empty($settings["embedding_model"])) {
			$models = \BigTreeAI::embeddingModels();
			$settings["embedding_model"] = $models[0]["id"] ?? "text-embedding-3-small";
			$changed = true;
		}

		if ($changed) {
			SettingService::updateInternalValue("bigtree-internal-ai-service", $settings, true);
			$ai = new BigTreeAI();
		}
	}

	if (!$ai->isEmbeddingsConfigured()) {
		SettingService::updateInternalValue("bigtree-internal-ai-embeddings-status", [
			"supported" => EmbeddingService::isSupported(),
			"table_ready" => true,
			"dimensions" => (int)\BigTreeAI::EMBEDDING_DIMENSIONS,
			"backfill" => "awaiting_configure",
			"backfill_note" => "Embeddings use OpenAI. Add an OpenAI API key under Developer → Configure → AI (Embedding API key when chat is xAI/Anthropic), enable vector embeddings, then Rebuild index.",
			"last_backfill_at" => null,
			"last_error" => null,
		], false);

		MigrationService::finish(507);

		echo BigTree::json([
			"complete" => true,
			"response" => "AI embeddings table ready — configure OpenAI embeddings + Rebuild index to fill it (507)",
		]);

		return;
	}

	// — batched reindex (503-style paging) —
	$page = isset($_GET["page"]) ? (int)$_GET["page"] : 0;
	$total_pages = isset($_GET["total_pages"]) ? (int)$_GET["total_pages"] : 0;

	if ($page < 1) {
		$probe = EmbeddingService::reindexAllBatch(0);

		if (!empty($probe["complete"]) || (int)($probe["pages"] ?? 0) < 1) {
			MigrationService::finish(507);

			echo BigTree::json([
				"complete" => true,
				"response" => ($probe["response"] ?? "Nothing to embed") . " (507)",
			]);

			return;
		}

		echo BigTree::json([
			"complete" => false,
			"response" => $probe["response"],
			"pages" => (int)$probe["pages"],
		]);

		return;
	}

	// Prefer the total declared on the first call (SPA sends total_pages).
	if ($total_pages < 1) {
		$probe = EmbeddingService::reindexAllBatch(0);
		$total_pages = max(1, (int)($probe["pages"] ?? 1));
	}

	$result = EmbeddingService::reindexAllBatch($page);
	$done = !empty($result["complete"]) || $page >= $total_pages;

	if ($done) {
		MigrationService::finish(507);

		echo BigTree::json([
			"complete" => true,
			"response" => ($result["response"] ?? "AI embeddings reindex complete") . " (507)",
		]);

		return;
	}

	echo BigTree::json([
		"complete" => false,
		"response" => $result["response"] ?? ("Indexed batch $page / $total_pages"),
	]);
