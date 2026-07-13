<?php
	// BigTree 5.0 — optional AI embeddings table (MySQL 9+ / MariaDB 11.7+)

	use BigTree\Services\MigrationService;
	use BigTree\Services\SettingService;

	MigrationService::begin(506);

	$supported = \BigTreeAI::vectorStoreSupported();
	$dimensions = (int)\BigTreeAI::EMBEDDING_DIMENSIONS;

	$status = [
		"supported" => $supported,
		"table_ready" => false,
		"dimensions" => $dimensions,
		"last_backfill_at" => null,
		"last_error" => null,
	];

	if (!$supported) {
		SettingService::updateInternalValue("bigtree-internal-ai-embeddings-status", $status, false);
		MigrationService::finish(506);

		echo BigTree::json([
			"complete" => true,
			"response" => "Skipped AI embeddings table (requires MySQL 9+ or MariaDB 11.7+) (506)",
		]);

		return;
	}

	// Create table when missing. VECTOR(n) is fixed for v1 (1536).
	if (!SQL::tableExists("bigtree_ai_embeddings")) {
		SQL::query("
			CREATE TABLE `bigtree_ai_embeddings` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`source_type` VARCHAR(32) NOT NULL,
				`source_id` VARCHAR(191) NOT NULL,
				`module_id` VARCHAR(191) NULL,
				`module_route` VARCHAR(191) NULL,
				`table_name` VARCHAR(191) NULL,
				`chunk_index` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				`title` VARCHAR(500) NOT NULL DEFAULT '',
				`content_text` MEDIUMTEXT NOT NULL,
				`content_hash` CHAR(64) NOT NULL DEFAULT '',
				`embedding` VECTOR($dimensions) NOT NULL,
				`model` VARCHAR(100) NOT NULL DEFAULT '',
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `source_chunk_model` (`source_type`, `source_id`, `chunk_index`, `model`),
				KEY `source` (`source_type`, `source_id`),
				KEY `module` (`module_id`),
				KEY `updated` (`updated_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
	}

	// Best-effort VECTOR INDEX (syntax differs; ignore failures).
	try {
		$dialect = \BigTreeAI::vectorDialect();

		if ($dialect === "mariadb") {
			$has = SQL::fetch(
				"SHOW INDEX FROM bigtree_ai_embeddings WHERE Key_name = 'embedding_vec'"
			);

			if (!$has) {
				SQL::query(
					"ALTER TABLE `bigtree_ai_embeddings`
					 ADD VECTOR INDEX `embedding_vec` (`embedding`) M=16 DISTANCE=cosine"
				);
			}
		}
		// MySQL 9 vector indexes (if/when available) can be added here later.
	} catch (Throwable $e) {
		// Non-fatal — ORDER BY DISTANCE still works without an ANN index.
	}

	$status["table_ready"] = SQL::tableExists("bigtree_ai_embeddings");
	SettingService::updateInternalValue("bigtree-internal-ai-embeddings-status", $status, false);

	MigrationService::finish(506);

	echo BigTree::json([
		"complete" => true,
		"response" => "Created bigtree_ai_embeddings table (506)",
	]);
