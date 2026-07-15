<?php
	// BigTree 5.0 — AI assistant chat: conversation + message store
	//
	// Creates bigtree_ai_conversations and bigtree_ai_messages (canonical DDL lives
	// on AIChatService so a first live turn can self-heal a missing table) and adds
	// the features.chat flag to the AI settings shape without enabling it.

	use BigTree\Services\AIChatService;
	use BigTree\Services\MigrationService;
	use BigTree\Services\SettingService;

	MigrationService::begin(508);

	AIChatService::ensureTables();

	// Ensure the AI settings carry a features.chat key (default off) so the SPA and
	// SystemConfigureService see a consistent shape after upgrade.
	$settings = BigTreeCMS::getSetting("bigtree-internal-ai-service");

	if (is_array($settings)) {
		$features = is_array($settings["features"] ?? null) ? $settings["features"] : [];

		if (!array_key_exists("chat", $features)) {
			$features["chat"] = false;
			$settings["features"] = $features;
			SettingService::updateInternalValue("bigtree-internal-ai-service", $settings, true);
		}
	}

	MigrationService::finish(508);

	echo BigTree::json([
		"complete" => true,
		"response" => "Created AI assistant chat tables (508)",
	]);
