SET SESSION sql_mode = "NO_AUTO_VALUE_ON_ZERO";
SET SESSION foreign_key_checks = 0;

DROP TABLE IF EXISTS `bigtree_404s`;
CREATE TABLE `bigtree_404s` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`broken_url` varchar(255) NOT NULL DEFAULT '',`redirect_url` varchar(255) NOT NULL DEFAULT '',`requests` int(11) unsigned NOT NULL DEFAULT '0',`ignored` char(2) NOT NULL DEFAULT '',PRIMARY KEY (`id`),KEY `broken_url` (`broken_url`),KEY `requests` (`requests`),KEY `ignored` (`ignored`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_audit_trail`;
CREATE TABLE `bigtree_audit_trail` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`user` int(11) unsigned NOT NULL,`table` varchar(255) NOT NULL,`entry` varchar(255) NOT NULL DEFAULT '',`type` varchar(255) NOT NULL,`date` datetime NOT NULL,PRIMARY KEY (`id`),KEY `user` (`user`),KEY `table` (`table`),KEY `entry` (`entry`),KEY `date` (`date`),FOREIGN KEY (`user`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_caches`;
CREATE TABLE `bigtree_caches` (`identifier` varchar(255) NOT NULL DEFAULT '', `key` varchar(10000) NOT NULL DEFAULT '', `value` longtext, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY `identifier` (`identifier`), KEY `key` (`key`), KEY `timestamp` (`timestamp`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_callout_groups`;
CREATE TABLE `bigtree_callout_groups` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) DEFAULT NULL, `callouts` text, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_callouts`;
CREATE TABLE `bigtree_callouts` (`id` varchar(255) NOT NULL, `name` varchar(255) NOT NULL DEFAULT '', `description` text NOT NULL, `display_default` varchar(255) NOT NULL, `display_field` varchar(255) NOT NULL, `resources` text NOT NULL, `level` int(11) unsigned NOT NULL, `position` int(11) unsigned NOT NULL, `extension` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `extension` (`extension`), FOREIGN KEY (`extension`) REFERENCES `bigtree_extensions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_extensions`;
CREATE TABLE `bigtree_extensions` (`id` varchar(255) NOT NULL DEFAULT '', `type` varchar(255) DEFAULT NULL, `name` varchar(255) DEFAULT NULL, `version` varchar(255) DEFAULT NULL, `last_updated` datetime DEFAULT NULL, `manifest` LONGTEXT DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_feeds`;
CREATE TABLE `bigtree_feeds` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `route` varchar(255) NOT NULL, `name` varchar(255) NOT NULL, `description` text NOT NULL, `type` varchar(255) NOT NULL, `table` varchar(255) NOT NULL, `fields` text NOT NULL, `options` text NOT NULL, `extension` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `route` (`route`), KEY `extension` (`extension`), FOREIGN KEY (`extension`) REFERENCES `bigtree_extensions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_field_types`;
CREATE TABLE `bigtree_field_types` (`id` varchar(255) NOT NULL DEFAULT '', `name` varchar(255) NOT NULL, `use_cases` text NOT NULL, `self_draw` char(2) DEFAULT NULL, `extension` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `extension` (`extension`), FOREIGN KEY (`extension`) REFERENCES `bigtree_extensions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_locks`;
CREATE TABLE `bigtree_locks` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`user` int(11) unsigned NOT NULL,`table` varchar(255) NOT NULL,`item_id` varchar(255) NOT NULL,`last_accessed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,`title` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `user` (`user`),KEY `table` (`table`),KEY `item_id` (`item_id`),FOREIGN KEY (`user`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_login_attempts`;
CREATE TABLE `bigtree_login_attempts` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `ip` int(11) DEFAULT NULL, `user` int(11) DEFAULT NULL, `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_login_bans`;
CREATE TABLE `bigtree_login_bans` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `ip` int(11) DEFAULT NULL, `user` int(11) DEFAULT NULL, `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP, `expires` datetime DEFAULT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_messages`;
CREATE TABLE `bigtree_messages` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`sender` int(11) unsigned NOT NULL,`recipients` text NOT NULL,`read_by` text NOT NULL,`subject` varchar(255) NOT NULL,`message` text NOT NULL,`response_to` int(11) unsigned NOT NULL,`date` datetime NOT NULL,PRIMARY KEY (`id`),KEY `sender` (`sender`),FOREIGN KEY (`sender`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_modules`;
CREATE TABLE `bigtree_modules` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `group` int(11) unsigned DEFAULT NULL, `name` varchar(255) NOT NULL DEFAULT '', `route` varchar(255) NOT NULL DEFAULT '', `class` varchar(255) NOT NULL DEFAULT '', `icon` varchar(255) NOT NULL, `gbp` text NOT NULL, `position` int(11) NOT NULL, `extension` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `group` (`group`), KEY `route` (`route`), KEY `extension` (`extension`), FOREIGN KEY (`group`) REFERENCES `bigtree_module_groups` (`id`) ON DELETE SET NULL, FOREIGN KEY (`extension`) REFERENCES `bigtree_extensions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_module_actions`;
CREATE TABLE `bigtree_module_actions` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned NOT NULL DEFAULT '0', `name` varchar(255) NOT NULL DEFAULT '', `route` varchar(255) NOT NULL DEFAULT '', `in_nav` char(2) NOT NULL DEFAULT '', `form` int(11) unsigned DEFAULT NULL, `view` int(11) unsigned DEFAULT NULL, `report` int(11) unsigned DEFAULT NULL, `class` varchar(255) NOT NULL, `level` int(11) NOT NULL, `position` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `module` (`module`), KEY `route` (`route`), KEY `in_nav` (`in_nav`), KEY `position` (`position`), FOREIGN KEY (`module`) REFERENCES `bigtree_modules` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_module_embeds`;
CREATE TABLE `bigtree_module_embeds` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned NOT NULL, `title` varchar(255) NOT NULL, `table` varchar(255) NOT NULL, `fields` text NOT NULL, `default_position` varchar(255) NOT NULL, `default_pending` char(2) NOT NULL, `css` varchar(255) NOT NULL, `hash` varchar(255) NOT NULL DEFAULT '', `redirect_url` varchar(255) NOT NULL DEFAULT '', `thank_you_message` text NOT NULL, `hooks` text, PRIMARY KEY (`id`), KEY `module` (`module`), FOREIGN KEY (`module`) REFERENCES `bigtree_modules` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_module_forms`;
CREATE TABLE `bigtree_module_forms` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned DEFAULT NULL, `title` varchar(255) NOT NULL, `table` varchar(255) NOT NULL, `fields` text NOT NULL, `default_position` varchar(255) NOT NULL, `return_view` int(11) unsigned DEFAULT NULL, `return_url` varchar(255) NOT NULL, `tagging` char(2) NOT NULL, `hooks` text, PRIMARY KEY (`id`), KEY `return_view` (`return_view`), KEY `module` (`module`), FOREIGN KEY (`return_view`) REFERENCES `bigtree_module_views` (`id`) ON DELETE SET NULL, FOREIGN KEY (`module`) REFERENCES `bigtree_modules` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_module_groups`;
CREATE TABLE `bigtree_module_groups` (`id` int(11) unsigned NOT NULL auto_increment, `name` varchar(255) NOT NULL, `route` varchar(255) NOT NULL, `position` int(11) NOT NULL default '0', `extension` varchar(255) default NULL, PRIMARY KEY  (`id`), KEY `route` (`route`), KEY `position` (`position`), KEY `extension` (`extension`), FOREIGN KEY (`extension`) REFERENCES `bigtree_extensions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_module_reports`;
CREATE TABLE `bigtree_module_reports` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned DEFAULT NULL, `title` varchar(255) NOT NULL DEFAULT '', `table` varchar(255) NOT NULL, `type` varchar(255) NOT NULL, `filters` text NOT NULL, `fields` text NOT NULL, `parser` varchar(255) NOT NULL DEFAULT '', `view` int(11) unsigned DEFAULT NULL, PRIMARY KEY (`id`), KEY `view` (`view`), KEY `module` (`module`), FOREIGN KEY (`module`) REFERENCES `bigtree_modules` (`id`) ON DELETE CASCADE, FOREIGN KEY (`view`) REFERENCES `bigtree_module_views` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_module_view_cache`;
CREATE TABLE `bigtree_module_view_cache` (`view` varchar(255) NOT NULL,`id` varchar(255) NOT NULL,`gbp_field` text NOT NULL,`published_gbp_field` text NOT NULL,`group_field` text NOT NULL,`sort_field` VARCHAR(255) NOT NULL,`group_sort_field` text NOT NULL,`position` int(11) NOT NULL,`approved` char(2) NOT NULL,`archived` char(2) NOT NULL,`featured` char(2) NOT NULL,`status` char(1) NOT NULL DEFAULT '',`pending_owner` int(11) unsigned NOT NULL,`column1` text NOT NULL,`column2` text NOT NULL,`column3` text NOT NULL,`column4` text NOT NULL,`column5` text NOT NULL,`column6` text NOT NULL,KEY `view` (`view`),KEY `group_field` (`group_field`(200)),KEY `group_sort_field` (`group_sort_field`(200)),KEY `id` (`id`),KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_module_views`;
CREATE TABLE `bigtree_module_views` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` int(11) unsigned DEFAULT NULL, `title` varchar(255) NOT NULL DEFAULT '', `description` text NOT NULL, `type` varchar(255) NOT NULL DEFAULT '', `table` varchar(255) NOT NULL DEFAULT '', `fields` text NOT NULL, `options` text NOT NULL, `actions` text NOT NULL, `preview_url` varchar(255) NOT NULL, `related_form` int(11) unsigned DEFAULT NULL, PRIMARY KEY (`id`), KEY `module` (`module`), KEY `related_form` (`related_form`), FOREIGN KEY (`module`) REFERENCES `bigtree_modules` (`id`) ON DELETE CASCADE, FOREIGN KEY (`related_form`) REFERENCES `bigtree_module_forms` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_page_revisions`;
CREATE TABLE `bigtree_page_revisions` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`page` int(11) unsigned NOT NULL DEFAULT '0',`title` varchar(255) NOT NULL DEFAULT '',`meta_keywords` text NOT NULL,`meta_description` text NOT NULL,`template` varchar(255) NOT NULL DEFAULT '',`external` varchar(255) NOT NULL DEFAULT '',`new_window` varchar(5) NOT NULL DEFAULT '',`resources` longtext NOT NULL,`author` int(11) unsigned NOT NULL,`saved` char(2) NOT NULL,`saved_description` text NOT NULL,`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `page` (`page`),KEY `saved` (`saved`),FOREIGN KEY (`page`) REFERENCES `bigtree_pages` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_pages`;
CREATE TABLE `bigtree_pages` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `trunk` char(2) NOT NULL, `parent` int(11) NOT NULL DEFAULT '0', `in_nav` varchar(5) NOT NULL, `nav_title` varchar(255) NOT NULL DEFAULT '', `route` varchar(255) NOT NULL, `path` text NOT NULL, `title` varchar(255) NOT NULL DEFAULT '', `meta_keywords` text NOT NULL, `meta_description` text NOT NULL, `seo_invisible` char(2) NOT NULL, `template` varchar(255) NOT NULL DEFAULT '', `external` varchar(255) NOT NULL DEFAULT '', `new_window` varchar(5) NOT NULL DEFAULT '', `resources` longtext NOT NULL, `archived` char(2) NOT NULL, `archived_inherited` char(2) NOT NULL, `publish_at` date DEFAULT NULL, `expire_at` date DEFAULT NULL, `max_age` int(11) unsigned NOT NULL, `last_edited_by` int(11) unsigned NOT NULL, `ga_page_views` int(11) unsigned NOT NULL, `position` int(11) NOT NULL DEFAULT '0', `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `parent` (`parent`), KEY `in_nav` (`in_nav`), KEY `route` (`route`), KEY `path` (`path`(200)), KEY `publish_at` (`publish_at`), KEY `expire_at` (`expire_at`), KEY `position` (`position`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_pending_changes`;
CREATE TABLE `bigtree_pending_changes` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `user` int(11) unsigned NULL, `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, `title` varchar(255) NOT NULL, `table` varchar(255) NOT NULL, `changes` longtext NOT NULL, `mtm_changes` longtext NOT NULL, `tags_changes` longtext NOT NULL, `item_id` int(11) unsigned DEFAULT NULL, `type` varchar(15) NOT NULL, `module` varchar(10) NOT NULL, `pending_page_parent` int(11) unsigned NOT NULL, `publish_hook` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `user` (`user`), KEY `item_id` (`item_id`), KEY `table` (`table`), KEY `pending_page_parent` (`pending_page_parent`), FOREIGN KEY (`user`) REFERENCES `bigtree_users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_resource_allocation`;
CREATE TABLE `bigtree_resource_allocation` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `module` varchar(255) DEFAULT NULL, `entry` varchar(255) DEFAULT NULL, `resource` int(11) unsigned DEFAULT NULL, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `resource` (`resource`), KEY `updated_at` (`updated_at`), FOREIGN KEY (`resource`) REFERENCES `bigtree_resources` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_resource_folders`;
CREATE TABLE `bigtree_resource_folders` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`parent` int(11) unsigned NOT NULL,`name` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `parent` (`parent`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_resources`;
CREATE TABLE `bigtree_resources` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `folder` int(11) unsigned DEFAULT NULL, `file` varchar(255) NOT NULL, `md5` varchar(255) NOT NULL, `date` datetime NOT NULL, `name` varchar(255) NOT NULL DEFAULT '', `type` varchar(255) NOT NULL DEFAULT '', `is_image` char(2) NOT NULL DEFAULT '', `height` int(11) unsigned NOT NULL DEFAULT '0', `width` int(11) unsigned NOT NULL DEFAULT '0', `crops` text NOT NULL, `thumbs` text NOT NULL, `list_thumb_margin` int(11) unsigned NOT NULL, PRIMARY KEY (`id`), KEY `folder` (`folder`), FOREIGN KEY (`folder`) REFERENCES `bigtree_resource_folders` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_route_history`;
CREATE TABLE `bigtree_route_history` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`old_route` varchar(255) NOT NULL,`new_route` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `old_route` (`old_route`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_settings`;
CREATE TABLE `bigtree_settings` (`id` varchar(255) NOT NULL DEFAULT '', `value` longblob NOT NULL, `type` varchar(255) NOT NULL, `options` longtext NOT NULL, `name` varchar(255) NOT NULL DEFAULT '', `description` text NOT NULL, `locked` char(2) NOT NULL, `system` char(2) NOT NULL, `encrypted` char(2) NOT NULL, `extension` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `extension` (`extension`), FOREIGN KEY (`extension`) REFERENCES `bigtree_extensions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_tags`;
CREATE TABLE `bigtree_tags` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`tag` varchar(255) NOT NULL,`metaphone` varchar(255) NOT NULL,`route` varchar(255) DEFAULT NULL,PRIMARY KEY (`id`),KEY `route` (`route`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_tags_rel`;
CREATE TABLE `bigtree_tags_rel` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `table` varchar(255) NOT NULL, `tag` int(11) unsigned NOT NULL, `entry` varchar(255) NOT NULL, PRIMARY KEY (`id`), KEY `tag` (`tag`), KEY `entry` (`entry`), FOREIGN KEY (`tag`) REFERENCES `bigtree_tags` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_templates`;
CREATE TABLE `bigtree_templates` (`id` varchar(255) NOT NULL DEFAULT '', `name` varchar(255) NOT NULL DEFAULT '', `routed` char(2) NOT NULL, `resources` text NOT NULL, `module` int(11) unsigned NOT NULL, `level` int(11) unsigned NOT NULL, `position` int(11) NOT NULL DEFAULT '0', `extension` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `routed` (`routed`), KEY `position` (`position`), KEY `extension` (`extension`), FOREIGN KEY (`extension`) REFERENCES `bigtree_extensions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bigtree_users`;
CREATE TABLE `bigtree_users` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`email` varchar(255) NOT NULL DEFAULT '',`password` varchar(255) NOT NULL DEFAULT '',`name` varchar(255) NOT NULL DEFAULT '',`company` varchar(255) NOT NULL DEFAULT '',`level` int(11) unsigned NOT NULL DEFAULT '0',`permissions` text NOT NULL,`alerts` text NOT NULL,`daily_digest` char(2) NOT NULL,`change_password_hash` varchar(255) NOT NULL,PRIMARY KEY (`id`),KEY `email` (`email`),KEY `password` (`password`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

DROP TABLE IF EXISTS `bigtree_user_sessions`;
CREATE TABLE `bigtree_user_sessions` (`id` varchar(255) NOT NULL DEFAULT '', `email` varchar(255) DEFAULT NULL, `chain` varchar(255) DEFAULT NULL, `csrf_token` varchar(255) DEFAULT NULL, `csrf_token_field` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `email` (`email`), KEY `chain` (`chain`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `bigtree_pages` (`id`, `trunk`, `parent`, `in_nav`, `nav_title`, `route`, `path`, `title`, `meta_keywords`, `meta_description`, `template`, `external`, `new_window`, `resources`, `archived`, `archived_inherited`, `position`, `created_at`, `updated_at`, `publish_at`, `expire_at`, `max_age`, `last_edited_by`, `ga_page_views`) VALUES (0,'on',-1,'on','BigTree Site','','','BigTree Site','','','home','','','{}','','',0,NOW(),NOW(),NULL,NULL,0,0,0);

INSERT INTO `bigtree_settings` (`id`,`type`,`name`,`description`) VALUES ('bigtree-internal-disable-page-tagging','checkbox','Disable Tagging in Pages','<p>Disables the ability for users to tag pages. Check this box if you do not use tags on your front end for related content.</p>');
INSERT INTO `bigtree_settings` (`id`,`value`,`system`) VALUES ('bigtree-internal-storage','{"service":"local"}','on');
INSERT INTO `bigtree_settings` (`id`, `value`, `type`, `options`, `name`, `description`, `locked`, `system`, `encrypted`, `extension`) VALUES ('bigtree-file-manager-thumbnail-sizes', X'5B0A202020207B0A2020202020202020227469746C65223A2022536D616C6C222C0A202020202020202022707265666978223A2022736D616C6C5F222C0A2020202020202020227769647468223A2022313530222C0A202020202020202022686569676874223A2022313030222C0A2020202020202020225F5F696E7465726E616C2D7469746C65223A2022536D616C6C220A202020207D2C0A202020207B0A2020202020202020227469746C65223A20224D656469756D222C0A202020202020202022707265666978223A20226D656469756D5F222C0A2020202020202020227769647468223A2022333030222C0A202020202020202022686569676874223A2022323030222C0A2020202020202020225F5F696E7465726E616C2D7469746C65223A20224D656469756D220A202020207D2C0A202020207B0A2020202020202020227469746C65223A20224C61726765222C0A202020202020202022707265666978223A20226C617267655F222C0A2020202020202020227769647468223A2022383030222C0A202020202020202022686569676874223A2022363030222C0A2020202020202020225F5F696E7465726E616C2D7469746C65223A20224C61726765220A202020207D0A5D', 'matrix', '{\n    \"columns\": [\n        {\n            \"id\": \"title\",\n            \"type\": \"text\",\n            \"title\": \"Title\",\n            \"display_title\": \"on\"\n        },\n        {\n            \"id\": \"prefix\",\n            \"type\": \"text\",\n            \"title\": \"File Prefix (i.e. thumb_)\",\n            \"display_title\": \"\"\n        },\n        {\n            \"id\": \"width\",\n            \"type\": \"text\",\n            \"title\": \"Width\",\n            \"display_title\": \"\"\n        },\n        {\n            \"id\": \"height\",\n            \"type\": \"text\",\n            \"title\": \"Height\",\n            \"display_title\": \"\"\n        }\n    ]\n}', 'File Manager Thumbnail Sizes', '', 'on', '', '', NULL);
INSERT INTO `bigtree_settings` (`id`, `value`, `type`, `options`, `name`, `description`, `locked`, `system`, `encrypted`) VALUES ('bigtree-internal-per-page', X'3135', 'text', '', 'Number of Items Per Page', '<p>This should be a numeric amount and controls the number of items per page in areas such as views, settings, users, etc.</p>', 'on', '', '');
INSERT INTO `bigtree_settings` (`id`,`value`,`system`) VALUES ('bigtree-internal-revision','203','on');
INSERT INTO `bigtree_settings` (`id`,`value`,`system`) VALUES ('bigtree-internal-security-policy','{}','on');
INSERT INTO `bigtree_settings` (`id`,`value`,`system`) VALUES ('bigtree-internal-media-settings','{}','on');

INSERT INTO `bigtree_templates` (`id`, `name`, `module`, `resources`, `position`, `level`, `routed`) VALUES ('home', 'Home', 0, '[]', 0, 2, ''), ('content', 'Content', 0, '[{"id":"page_header","title":"Page Header","subtitle":"","type":"text","validation":"","seo_h1":"on","sub_type":"","wrapper":"","name":""},{"id":"page_content","title":"Page Content","subtitle":"","type":"html","validation":"","seo_body":"on","wrapper":"","name":""}]', 1, 0, '');

SET SESSION foreign_key_checks = 1;