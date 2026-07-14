<?php
	// BigTree 5.0 — optional AI embeddings table (MySQL 9+ / MariaDB 11.7+)

	use BigTree\Services\EmbeddingService;
	use BigTree\Services\MigrationService;
	use BigTree\Services\SettingService;

	MigrationService::begin(506);

	$supported = \BigTreeAI::vectorStoreSupported();

	if (!$supported) {
		SettingService::updateInternalValue("bigtree-internal-ai-embeddings-status", [
			"supported" => false,
			"table_ready" => false,
			"dimensions" => (int)\BigTreeAI::EMBEDDING_DIMENSIONS,
			"last_backfill_at" => null,
			"last_error" => null,
		], false);
		MigrationService::finish(506);

		echo BigTree::json([
			"complete" => true,
			"response" => "Skipped AI embeddings table (requires MySQL 9+ or MariaDB 11.7+) (506)",
		]);

		return;
	}

	// Canonical DDL lives on EmbeddingService so a host that upgrades its DB after
	// this migration ran can still create the table (Configure → AI / reindex).
	EmbeddingService::ensureTable();

	MigrationService::finish(506);

	echo BigTree::json([
		"complete" => true,
		"response" => "Created bigtree_ai_embeddings table (506)",
	]);
