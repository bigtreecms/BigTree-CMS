<?php
	// BigTree 4.6 — REST API foundation

	// Adds token_version column to users (stateless JWT revocation mechanism).
	SQL::query("ALTER TABLE `bigtree_users` ADD COLUMN `token_version` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `change_password_hash`");

	// Refresh tokens: rotated per /auth/refresh, with theft detection via family_id.
	SQL::query("
		CREATE TABLE `bigtree_refresh_tokens` (
			`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			`user_id` INT(11) UNSIGNED NOT NULL,
			`token_hash` CHAR(64) NOT NULL UNIQUE,
			`family_id` CHAR(32) NOT NULL,
			`issued_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`expires_at` TIMESTAMP NULL DEFAULT NULL,
			`rotated_to` BIGINT UNSIGNED NULL DEFAULT NULL,
			`revoked` TINYINT(1) NOT NULL DEFAULT 0,
			`user_agent` VARCHAR(255) DEFAULT NULL,
			`ip` VARCHAR(45) DEFAULT NULL,
			KEY `user_id` (`user_id`),
			KEY `family_id` (`family_id`),
			KEY `expires_at` (`expires_at`),
			CONSTRAINT `bigtree_refresh_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
	");

	// Passkey challenge ledger: replaces $_SESSION-stored challenges so the API stays stateless.
	SQL::query("
		CREATE TABLE `bigtree_passkey_challenges` (
			`id` CHAR(32) PRIMARY KEY,
			`challenge` VARCHAR(255) NOT NULL,
			`user_id` INT(11) UNSIGNED NULL DEFAULT NULL,
			`purpose` ENUM('auth','register') NOT NULL,
			`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`consumed` TINYINT(1) NOT NULL DEFAULT 0,
			KEY `created_at` (`created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
	");

	// API rate-limit buckets (fixed window).
	SQL::query("
		CREATE TABLE `bigtree_api_rate_limits` (
			`bucket` VARCHAR(64) NOT NULL,
			`window_start` TIMESTAMP NOT NULL DEFAULT '1970-01-02 00:00:01',
			`count` INT(11) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (`bucket`, `window_start`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
	");

	// Audit-trail context sibling table (so the legacy bigtree_audit_trail schema stays unchanged).
	SQL::query("
		CREATE TABLE `bigtree_audit_trail_context` (
			`audit_id` INT(11) UNSIGNED NOT NULL PRIMARY KEY,
			`ip` VARCHAR(45) DEFAULT NULL,
			`user_agent` VARCHAR(255) DEFAULT NULL,
			`request_id` CHAR(32) DEFAULT NULL,
			`method` VARCHAR(8) DEFAULT NULL,
			`path` VARCHAR(255) DEFAULT NULL,
			CONSTRAINT `bigtree_audit_trail_context_fk` FOREIGN KEY (`audit_id`) REFERENCES `bigtree_audit_trail` (`id`) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
	");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.6 REST API foundation (503)"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 503);
