<?php
	// BigTree 5.0 — AI assistant audit surfacing (Phase 5)
	//
	// Adds a `via` column to bigtree_audit_trail_context so a change made through
	// the AI assistant (approved proposal) can be recorded in the audit trail and
	// distinguished in the audit UI. The column is null for ordinary REST changes;
	// AI approvals write "ai_assistant". Idempotent: guarded ADD COLUMN.

	use BigTree\Services\MigrationService;

	MigrationService::begin(510);

	if (SQL::tableExists("bigtree_audit_trail_context") && !SQL::fetch("SHOW COLUMNS FROM `bigtree_audit_trail_context` LIKE 'via'")) {
		SQL::query("ALTER TABLE `bigtree_audit_trail_context` ADD COLUMN `via` VARCHAR(32) DEFAULT NULL AFTER `path`");
	}

	MigrationService::finish(510);

	echo BigTree::json([
		"complete" => true,
		"response" => "Added AI-assistant audit source column (510)",
	]);
