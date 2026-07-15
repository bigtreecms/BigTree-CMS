<?php
	// BigTree 5.0 — AI assistant proposals: staged mutation store (Phase 3)
	//
	// Creates bigtree_ai_proposals, the server-side store for two-phase mutating
	// tools (create_page, …). A mutating tool stages a validated proposal here; the
	// change runs only when the user approves it and permission is re-checked. The
	// canonical DDL lives on ProposalStore so a first live proposal can self-heal a
	// missing table.

	use BigTree\Services\AI\ProposalStore;
	use BigTree\Services\MigrationService;

	MigrationService::begin(509);

	ProposalStore::ensureTables();

	MigrationService::finish(509);

	echo BigTree::json([
		"complete" => true,
		"response" => "Created AI assistant proposal store (509)",
	]);
