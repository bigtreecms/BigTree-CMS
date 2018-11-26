<?php
	// BigTree 4.2.20

	sqlquery("ALTER TABLE `bigtree_404s` ADD COLUMN `get_vars` VARCHAR(255) NOT NULL AFTER `broken_url`");
	sqlquery("ALTER TABLE `bigtree_users` ADD COLUMN `2fa_secret` VARCHAR(255) NOT NULL AFTER `password`");
	sqlquery("ALTER TABLE `bigtree_users` ADD COLUMN `2fa_login_token` VARCHAR(255) NOT NULL AFTER `2fa_secret`");

	// Add a setting for storing the contact information of deleted users for use in audits
	sqlquery("INSERT INTO bigtree_settings (`id`, `value`, `system`) VALUES ('bigtree-internal-deleted-users', '[]', 'on')");

	// Drop the foreign key constraint on the audit trail which previously deleted trails for non-existant users
	$table_description = BigTree::describeTable("bigtree_audit_trail");

	foreach ($table_description["foreign_keys"] as $key => $data) {
		if ($data["local_columns"][0] == "user") {
			sqlquery("ALTER TABLE `bigtree_audit_trail` DROP FOREIGN KEY `$key`");
		}
	}
	
	$admin->updateInternalSettingValue("bigtree-internal-revision", 205);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.2.20..."
	]);
	