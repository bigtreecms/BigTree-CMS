<?php
	// Backup the tables we're destroying
	SQL::backup(SERVER_ROOT."cache/backup-4.3.sql", [
		"bigtree_callout_groups",
		"bigtree_callouts",
		"bigtree_extensions",
		"bigtree_feeds",
		"bigtree_field_types",
		"bigtree_module_actions",
		"bigtree_module_embeds",
		"bigtree_module_forms",
		"bigtree_module_groups",
		"bigtree_module_reports",
		"bigtree_module_views",
		"bigtree_modules",
		"bigtree_settings"
		"bigtree_templates",
	]);

	SQL::query("DROP TABLE `bigtree_callout_groups`");
	SQL::query("DROP TABLE `bigtree_callouts`");
	SQL::query("DROP TABLE `bigtree_extensions`");
	SQL::query("DROP TABLE `bigtree_feeds`");
	SQL::query("DROP TABLE `bigtree_field_types`");
	SQL::query("DROP TABLE `bigtree_module_actions`");
	SQL::query("DROP TABLE `bigtree_module_embeds`");
	SQL::query("DROP TABLE `bigtree_module_forms`");
	SQL::query("DROP TABLE `bigtree_module_groups`");
	SQL::query("DROP TABLE `bigtree_module_reports`");
	SQL::query("DROP TABLE `bigtree_module_views`");
	SQL::query("DROP TABLE `bigtree_modules`");
	SQL::query("DROP TABLE `bigtree_templates`");
	SQL::query("ALTER TABLE `bigtree_settings` DROP COLUMN `type`");
	SQL::query("ALTER TABLE `bigtree_settings` DROP COLUMN `settings`");
	SQL::query("ALTER TABLE `bigtree_settings` DROP COLUMN `name`");
	SQL::query("ALTER TABLE `bigtree_settings` DROP COLUMN `description`");
	SQL::query("ALTER TABLE `bigtree_settings` DROP COLUMN `locked`");
	SQL::query("ALTER TABLE `bigtree_settings` DROP COLUMN `system`");
	SQL::query("ALTER TABLE `bigtree_settings` DROP COLUMN `extension`");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgraded to BigTree 4.4"
	]);
