-- NOTE: This file is maintained to be importable with FOREIGN_KEY_CHECKS=1.
-- All FOREIGN KEY constraints are defined as trailing `ALTER TABLE ... ADD CONSTRAINT`
-- statements after every table is created. When adding a new table with foreign keys,
-- append its `CREATE TABLE` anywhere above and add its constraint to the ALTER block at
-- the end of this file -- do not place inline FOREIGN KEY clauses in CREATE TABLE bodies.
SET SESSION sql_mode = "NO_AUTO_VALUE_ON_ZERO";

DROP TABLE IF EXISTS `bigtree_404s`;
CREATE TABLE `bigtree_404s` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`broken_url` varchar(1024) NOT NULL DEFAULT '',`get_vars` varchar(1024) NOT NULL,`redirect_url` varchar(1024) NOT NULL DEFAULT '',`requests` int(11) unsigned NOT NULL DEFAULT '0',`ignored` char(2) NOT NULL DEFAULT '',`site_key` varchar(1024) DEFAULT NULL,PRIMARY KEY (`id`),KEY `broken_url` (`broken_url`),KEY `requests` (`requests`),KEY `ignored` (`ignored`),KEY `site_key` (`site_key`),KEY `get_vars` (`get_vars`),KEY `redirect_url` (`redirect_url`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_audit_trail`;
CREATE TABLE `bigtree_audit_trail` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`user` int(11) unsigned NOT NULL,`table` varchar(1024) NOT NULL,`entry` varchar(1024) NOT NULL DEFAULT '',`type` varchar(1024) NOT NULL,`date` datetime NOT NULL,PRIMARY KEY (`id`),KEY `user` (`user`),KEY `table` (`table`),KEY `entry` (`entry`),KEY `date` (`date`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_caches`;
CREATE TABLE `bigtree_caches` (`identifier` varchar(1024) NOT NULL DEFAULT '', `key` varchar(10000) NOT NULL DEFAULT '', `value` longtext, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, `expires` TIMESTAMP DEFAULT NULL, KEY `identifier` (`identifier`), KEY `key` (`key`), KEY `timestamp` (`timestamp`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_locks`;
CREATE TABLE `bigtree_locks` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`user` int(11) unsigned NOT NULL,`table` varchar(1024) NOT NULL,`item_id` varchar(1024) NOT NULL,`last_accessed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`title` varchar(1024) NOT NULL,PRIMARY KEY (`id`),KEY `user` (`user`),KEY `table` (`table`),KEY `item_id` (`item_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_login_attempts`;
CREATE TABLE `bigtree_login_attempts` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `ip` int(11) DEFAULT NULL, `user` int(11) DEFAULT NULL, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_login_bans`;
CREATE TABLE `bigtree_login_bans` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `ip` int(11) DEFAULT NULL, `user` int(11) DEFAULT NULL, `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP, `expires` datetime DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_messages`;
CREATE TABLE `bigtree_messages` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`sender` int(11) unsigned NOT NULL,`recipients` text NOT NULL,`read_by` text NOT NULL,`subject` varchar(1024) NOT NULL,`message` text NOT NULL,`response_to` int(11) unsigned NOT NULL,`date` datetime NOT NULL,PRIMARY KEY (`id`),KEY `sender` (`sender`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_module_view_cache`;
CREATE TABLE `bigtree_module_view_cache` (`view` varchar(1024) NOT NULL,`id` varchar(1024) NOT NULL,`gbp_field` text NOT NULL,`published_gbp_field` text NOT NULL,`group_field` text NOT NULL,`sort_field` varchar(1024) NOT NULL,`group_sort_field` text NOT NULL,`position` int(11) NOT NULL,`approved` char(2) NOT NULL,`archived` char(2) NOT NULL,`featured` char(2) NOT NULL,`status` char(1) NOT NULL DEFAULT '',`pending_owner` int(11) unsigned NOT NULL,`column1` text NOT NULL,`column2` text NOT NULL,`column3` text NOT NULL,`column4` text NOT NULL,`column5` text NOT NULL,`column6` text NOT NULL,KEY `view` (`view`),KEY `group_field` (`group_field`(200)),KEY `group_sort_field` (`group_sort_field`(200)),KEY `id` (`id`),KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_open_graph`;
CREATE TABLE `bigtree_open_graph` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `table` varchar(1024) NOT NULL DEFAULT '', `entry` int(11) unsigned NOT NULL, `type` varchar(1024) DEFAULT NULL, `title` varchar(1024) DEFAULT NULL, `description` text, `image` varchar(1024) DEFAULT NULL, `image_width` int(11) DEFAULT NULL, `image_height` int(11) DEFAULT NULL, PRIMARY KEY (`id`), KEY `table` (`table`), KEY `entry` (`entry`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_page_revisions`;
CREATE TABLE `bigtree_page_revisions` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`page` int(11) unsigned NOT NULL DEFAULT '0',`title` varchar(1024) NOT NULL DEFAULT '',`meta_description` text NOT NULL,`template` varchar(1024) NOT NULL DEFAULT '',`external` varchar(1024) NOT NULL DEFAULT '',`new_window` varchar(5) NOT NULL DEFAULT '',`resources` longtext NOT NULL,`author` int(11) unsigned NOT NULL,`saved` char(2) NOT NULL,`saved_description` text NOT NULL,`resource_allocation` text NOT NULL,`has_deleted_resources` char(2) NOT NULL,`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `page` (`page`),KEY `saved` (`saved`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_pages`;
CREATE TABLE `bigtree_pages` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`trunk` char(2) NOT NULL,`parent` int(11) NOT NULL DEFAULT '0',`in_nav` varchar(5) NOT NULL,`nav_title` varchar(1024) NOT NULL DEFAULT '',`route` varchar(1024) NOT NULL,`path` text NOT NULL,`title` varchar(1024) NOT NULL DEFAULT '',`meta_keywords` text NOT NULL,`meta_description` text NOT NULL,`open_graph` longtext,`seo_invisible` char(2) NOT NULL,`template` varchar(1024) NOT NULL DEFAULT '',`external` varchar(1024) NOT NULL DEFAULT '',`new_window` varchar(5) NOT NULL DEFAULT '',`resources` longtext NOT NULL,`archived` char(2) NOT NULL,`archived_inherited` char(2) NOT NULL,`publish_at` datetime DEFAULT NULL,`expire_at` datetime DEFAULT NULL,`max_age` int(11) unsigned NOT NULL,`last_edited_by` int(11) unsigned NOT NULL,`ga_page_views` int(11) unsigned NOT NULL,`position` int(11) NOT NULL DEFAULT '0',`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),KEY `parent` (`parent`),KEY `in_nav` (`in_nav`),KEY `route` (`route`),KEY `path` (`path`(200)),KEY `publish_at` (`publish_at`),KEY `expire_at` (`expire_at`),KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_pending_changes`;
CREATE TABLE `bigtree_pending_changes` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`user` int(11) unsigned DEFAULT NULL,`date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,`title` varchar(1024) NOT NULL,`table` varchar(1024) NOT NULL,`changes` longtext NOT NULL,`mtm_changes` longtext NOT NULL,`tags_changes` longtext NOT NULL,`open_graph_changes` longtext NOT NULL,`item_id` int(11) unsigned DEFAULT NULL,`type` varchar(15) NOT NULL,`module` varchar(1024) DEFAULT NULL,`pending_page_parent` int(11) unsigned NOT NULL,`publish_hook` varchar(1024) DEFAULT NULL,PRIMARY KEY (`id`),KEY `user` (`user`),KEY `item_id` (`item_id`),KEY `table` (`table`),KEY `pending_page_parent` (`pending_page_parent`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_resource_allocation`;
CREATE TABLE `bigtree_resource_allocation` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `table` varchar(1024) DEFAULT NULL, `entry` varchar(1024) DEFAULT NULL, `resource` int(11) unsigned DEFAULT NULL, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `resource` (`resource`), KEY `updated_at` (`updated_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_resource_folders`;
CREATE TABLE `bigtree_resource_folders` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`parent` int(11) unsigned NOT NULL,`name` varchar(1024) NOT NULL,PRIMARY KEY (`id`),KEY `parent` (`parent`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_resources`;
CREATE TABLE `bigtree_resources` ( `id` int(11) unsigned NOT NULL AUTO_INCREMENT, `location` varchar(1024) DEFAULT '', `folder` int(11) unsigned DEFAULT NULL, `file` varchar(1024) NOT NULL, `md5` varchar(1024) DEFAULT '', `date` datetime NOT NULL, `name` varchar(1024) NOT NULL DEFAULT '', `type` varchar(1024) NOT NULL DEFAULT '', `mimetype` varchar(1024) DEFAULT '', `metadata` longtext NOT NULL, `is_image` char(2) NOT NULL DEFAULT '', `is_video` char(2) NOT NULL DEFAULT '', `height` int(11) unsigned DEFAULT '0', `width` int(11) unsigned DEFAULT '0', `size` int(11) unsigned DEFAULT NULL, `crops` text NOT NULL, `thumbs` text NOT NULL, `video_data` longtext, `last_updated` datetime DEFAULT NULL, `file_last_updated` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `folder` (`folder`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_route_history`;
CREATE TABLE `bigtree_route_history` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`old_route` varchar(1024) NOT NULL,`new_route` varchar(1024) NOT NULL,PRIMARY KEY (`id`),KEY `old_route` (`old_route`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_sessions`;
CREATE TABLE `bigtree_sessions` (`id` varchar(32) NOT NULL,`last_accessed` int(10) unsigned DEFAULT NULL,`ip_address` varchar(1024) NOT NULL,`user_agent` text NOT NULL,`data` longtext,`is_login` char(2) NOT NULL DEFAULT '',`logged_in_user` int(11) unsigned DEFAULT NULL,PRIMARY KEY (`id`),KEY `fk_logged_in_user` (`logged_in_user`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_settings`;
CREATE TABLE `bigtree_settings` (`id` varchar(1024) NOT NULL DEFAULT '', `value` longblob NOT NULL, `encrypted` char(2) NOT NULL, `extension` varchar(1024) DEFAULT NULL, PRIMARY KEY (`id`), KEY `extension` (`extension`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_tags`;
CREATE TABLE `bigtree_tags` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`tag` varchar(1024) NOT NULL,`metaphone` varchar(1024) NOT NULL,`route` varchar(1024) DEFAULT NULL,`usage_count` int(11) unsigned NOT NULL,PRIMARY KEY (`id`),KEY `route` (`route`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_tags_rel`;
CREATE TABLE `bigtree_tags_rel` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `table` varchar(1024) NOT NULL, `tag` int(11) unsigned NOT NULL, `entry` varchar(1024) NOT NULL, PRIMARY KEY (`id`), KEY `tag` (`tag`), KEY `entry` (`entry`), UNIQUE KEY `tag_relation` (`table`(191), `entry`(191), `tag`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_users`;
CREATE TABLE `bigtree_users` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`email` varchar(1024) NOT NULL DEFAULT '',`password` varchar(1024) NOT NULL DEFAULT '',`new_hash` char(2) NOT NULL,`2fa_secret` varchar(1024) NOT NULL,`2fa_login_token` varchar(1024) NOT NULL,`name` varchar(1024) NOT NULL DEFAULT '',`company` varchar(1024) NOT NULL DEFAULT '',`level` int(11) unsigned NOT NULL DEFAULT '0',`permissions` text NOT NULL,`alerts` text NOT NULL,`daily_digest` char(2) NOT NULL,`timezone` varchar(1024) NOT NULL,`change_password_hash` varchar(1024) NOT NULL,`token_version` int(11) unsigned NOT NULL DEFAULT '1',PRIMARY KEY (`id`),KEY `email` (`email`),KEY `password` (`password`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_user_sessions`;
CREATE TABLE `bigtree_user_sessions` (`id` varchar(1024) NOT NULL DEFAULT '', `email` varchar(1024) DEFAULT NULL, `chain` varchar(1024) DEFAULT NULL, `csrf_token` varchar(1024) DEFAULT NULL, `csrf_token_field` varchar(1024) DEFAULT NULL, PRIMARY KEY (`id`), KEY `email` (`email`), KEY `chain` (`chain`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_user_passkeys`;
CREATE TABLE `bigtree_user_passkeys` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `user` int(10) unsigned NOT NULL, `credential_id` text NOT NULL, `public_key` text NOT NULL, `sign_count` int(10) unsigned NOT NULL DEFAULT '0', `name` varchar(255) NOT NULL DEFAULT '', `aaguid` varchar(36) NOT NULL DEFAULT '', `transports` varchar(255) NOT NULL DEFAULT '', `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, `last_used` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `user_idx` (`user`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `bigtree_refresh_tokens`;
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
	KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `bigtree_passkey_challenges`;
CREATE TABLE `bigtree_passkey_challenges` (
	`id` CHAR(32) PRIMARY KEY,
	`challenge` VARCHAR(255) NOT NULL,
	`user_id` INT(11) UNSIGNED NULL DEFAULT NULL,
	`purpose` ENUM('auth','register') NOT NULL,
	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`consumed` TINYINT(1) NOT NULL DEFAULT 0,
	KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `bigtree_api_rate_limits`;
CREATE TABLE `bigtree_api_rate_limits` (
	`bucket` VARCHAR(64) NOT NULL,
	`window_start` TIMESTAMP NOT NULL DEFAULT '1970-01-02 00:00:01',
	`count` INT(11) UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (`bucket`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `bigtree_audit_trail_context`;
CREATE TABLE `bigtree_audit_trail_context` (
	`audit_id` INT(11) UNSIGNED NOT NULL PRIMARY KEY,
	`ip` VARCHAR(45) DEFAULT NULL,
	`user_agent` VARCHAR(255) DEFAULT NULL,
	`request_id` CHAR(32) DEFAULT NULL,
	`method` VARCHAR(8) DEFAULT NULL,
	`path` VARCHAR(255) DEFAULT NULL,
	`via` VARCHAR(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration ledger (017 Phase 1). No FK by design — it references revision files, not rows.
-- Fresh installs intentionally ship with an EMPTY ledger; the seeded
-- `bigtree-internal-revision` setting below is the floor. Phase 2's pending() treats every
-- revision <= that floor as applied even when absent from this table, which keeps fresh and
-- upgraded installs consistent without seeding ~40 historical rows here. Do NOT seed rows.
DROP TABLE IF EXISTS `bigtree_migrations`;
CREATE TABLE `bigtree_migrations` (
	`revision`    INT(11) UNSIGNED NOT NULL PRIMARY KEY,
	`name`        VARCHAR(255) NOT NULL DEFAULT '',
	`checksum`    CHAR(64) NOT NULL DEFAULT '',
	`applied_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`duration_ms` INT(11) UNSIGNED NULL DEFAULT NULL,
	`success`     TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Foreign key constraints (added after all tables exist so this file imports with FOREIGN_KEY_CHECKS=1).
ALTER TABLE `bigtree_locks` ADD CONSTRAINT `bigtree_locks_ibfk_1` FOREIGN KEY (`user`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE;
ALTER TABLE `bigtree_messages` ADD CONSTRAINT `bigtree_messages_ibfk_1` FOREIGN KEY (`sender`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE;
ALTER TABLE `bigtree_page_revisions` ADD CONSTRAINT `bigtree_page_revisions_ibfk_1` FOREIGN KEY (`page`) REFERENCES `bigtree_pages` (`id`) ON DELETE CASCADE;
ALTER TABLE `bigtree_pending_changes` ADD CONSTRAINT `bigtree_pending_changes_ibfk_1` FOREIGN KEY (`user`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE;
ALTER TABLE `bigtree_resources` ADD CONSTRAINT `bigtree_resources_ibfk_1` FOREIGN KEY (`folder`) REFERENCES `bigtree_resource_folders` (`id`) ON DELETE SET NULL;
ALTER TABLE `bigtree_resource_allocation` ADD CONSTRAINT `bigtree_resource_allocation_ibfk_1` FOREIGN KEY (`resource`) REFERENCES `bigtree_resources` (`id`) ON DELETE CASCADE;
ALTER TABLE `bigtree_sessions` ADD CONSTRAINT `fk_logged_in_user` FOREIGN KEY (`logged_in_user`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE;
ALTER TABLE `bigtree_tags_rel` ADD CONSTRAINT `bigtree_tags_rel_ibfk_1` FOREIGN KEY (`tag`) REFERENCES `bigtree_tags` (`id`) ON DELETE CASCADE;
ALTER TABLE `bigtree_refresh_tokens` ADD CONSTRAINT `bigtree_refresh_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE;
ALTER TABLE `bigtree_audit_trail_context` ADD CONSTRAINT `bigtree_audit_trail_context_fk` FOREIGN KEY (`audit_id`) REFERENCES `bigtree_audit_trail` (`id`) ON DELETE CASCADE;

INSERT INTO `bigtree_pages` (`id`, `trunk`, `parent`, `in_nav`, `nav_title`, `route`, `path`, `title`, `meta_keywords`, `meta_description`, `template`, `external`, `new_window`, `resources`, `archived`, `archived_inherited`, `position`, `created_at`, `updated_at`, `publish_at`, `expire_at`, `max_age`, `last_edited_by`, `ga_page_views`) VALUES (0,'on',-1,'on','BigTree Site','','','BigTree Site','','','home','','','{}','','',0,NOW(),NOW(),NULL,NULL,0,0,0);

INSERT INTO `bigtree_settings` (`id`,`value`) VALUES ('bigtree-internal-storage','{"Service":"local"}');
INSERT INTO `bigtree_settings` (`id`,`value`) VALUES ('bigtree-internal-revision','507');
INSERT INTO `bigtree_settings` (`id`,`value`) VALUES ('bigtree-internal-security-policy','{"password":{"invitations": "on"}}');
INSERT INTO `bigtree_settings` (`id`,`value`) VALUES ('bigtree-internal-deleted-users','{}');
INSERT INTO `bigtree_settings` (`id`, `value`) VALUES ('bigtree-file-metadata-fields', '{}');
INSERT INTO `bigtree_settings` (`id`, `value`) VALUES ('bigtree-internal-per-page', '15');