<?php
	// BigTree 5.0 — AI assistant audit #5 (Phase 1, A5)
	//
	// bigtree_tags_rel had no unique key on (table, entry, tag), so merging tags
	// gave every record that carried both tags two identical relation rows: the tag
	// rendered twice on the front end and the usage count (a COUNT(*) of rows) was
	// permanently wrong. De-dupe what is already stored, then make it structural.
	//
	// Idempotent: the de-dupe is a no-op once clean and the index is guarded.

	use BigTree\Services\MigrationService;

	MigrationService::begin(511);

	if (SQL::tableExists("bigtree_tags_rel")) {
		// Keep the lowest id of each (table, entry, tag) triple.
		SQL::query(
			"DELETE r FROM `bigtree_tags_rel` r
			 JOIN `bigtree_tags_rel` keep
			   ON keep.`table` = r.`table` AND keep.`entry` = r.`entry` AND keep.`tag` = r.`tag`
			  AND keep.`id` < r.`id`"
		);

		if (!SQL::fetch("SHOW INDEX FROM `bigtree_tags_rel` WHERE Key_name = 'tag_relation'")) {
			SQL::query(
				"ALTER TABLE `bigtree_tags_rel`
				 ADD UNIQUE KEY `tag_relation` (`table`(191), `entry`(191), `tag`)"
			);
		}

		// The row counts the de-dupe changed have to be reflected in the tags' own
		// cached usage counts, which are a COUNT(*) over this table.
		SQL::query(
			"UPDATE `bigtree_tags` t
			 SET t.`usage_count` = (SELECT COUNT(*) FROM `bigtree_tags_rel` r WHERE r.`tag` = t.`id`)"
		);
	}

	MigrationService::finish(511);

	echo BigTree::json([
		"complete" => true,
		"response" => "De-duplicated tag relations and made them unique (511)",
	]);
