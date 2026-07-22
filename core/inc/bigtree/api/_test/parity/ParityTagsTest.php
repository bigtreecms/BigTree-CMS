<?php
	/**
	 * L1 parity: TagService vs master tags create/delete/merge (p0/tags.md).
	 */

	use BigTree\Services\TagService;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;

	function test_parity_tags_create_normalizes_and_inserts() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();
		$req = parity_request(1, 1, [], ["tag" => "Hello World"]);
		$created_ids = [];

		try {
			$res = $svc->create($req);
			T::equals($res->status, 201, "create returns 201");
			$data = parity_data($res);
			$created_ids[] = (int)$data["id"];

			T::equals($data["tag"], "hello world", "tag normalized to lowercase alnum+space");
			T::equals($data["usage_count"], 0, "usage_count starts at 0");
			T::ok($data["route"] !== "", "route is non-empty");

			$row = SQL::fetch("SELECT tag, metaphone, route, usage_count FROM bigtree_tags WHERE id = ?", $data["id"]);
			T::equals($row["tag"], "hello world", "DB tag column matches");
			T::equals($row["metaphone"], metaphone("hello world"), "metaphone set");
			T::equals((int)$row["usage_count"], 0, "DB usage_count 0");
		} finally {
			parity_delete_tags(...$created_ids);
		}
	}

	function test_parity_tags_create_strips_punctuation() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();
		$created_ids = [];

		try {
			$res = $svc->create(parity_request(1, 1, [], ["tag" => "  Foo-Bar!! "]));
			$data = parity_data($res);
			$created_ids[] = (int)$data["id"];
			// Master + TagService: preg_replace('/[^a-zA-Z0-9 ]/', '', ...) → "FooBar" → "foobar"
			T::equals($data["tag"], "foobar", "punctuation stripped before lowercase");
		} finally {
			parity_delete_tags(...$created_ids);
		}
	}

	function test_parity_tags_create_duplicate_returns_existing() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();
		$created_ids = [];

		try {
			$first = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity Dup Tag"])));
			$created_ids[] = (int)$first["id"];

			$second_res = $svc->create(parity_request(1, 1, [], ["tag" => "parity dup tag"]));
			T::equals($second_res->status, 200, "duplicate create returns 200 (idempotent)");
			$second = parity_data($second_res);
			T::equals((int)$second["id"], (int)$first["id"], "same id returned");

			$count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags WHERE tag = ?", "parity dup tag");
			T::equals($count, 1, "exactly one row for normalized tag");
		} finally {
			parity_delete_tags(...$created_ids);
		}
	}

	function test_parity_tags_create_empty_rejects() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();

		T::throws(
			function () use ($svc) {
				$svc->create(parity_request(1, 1, [], ["tag" => "!!!"]));
			},
			BadRequestException::class,
			"empty after normalize throws empty_tag"
		);
	}

	function test_parity_tags_delete_removes_relations() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();
		$tag_id = 0;
		$page_id = 0;

		try {
			$tag = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity Delete Me"])));
			$tag_id = (int)$tag["id"];

			// Attach to a real page if one exists (About = 1 on example-site).
			$page_id = (int)SQL::fetchSingle("SELECT id FROM bigtree_pages WHERE id > 0 LIMIT 1");

			if ($page_id > 0) {
				SQL::insert("bigtree_tags_rel", [
					"table" => "bigtree_pages",
					"tag" => $tag_id,
					"entry" => (string)$page_id,
				]);
			}

			$res = $svc->delete(parity_request(1, 1, ["id" => $tag_id]));
			T::equals($res->status, 204, "delete returns 204");
			T::ok(!SQL::exists("bigtree_tags", $tag_id), "tag row removed");
			$rels = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ?", $tag_id);
			T::equals($rels, 0, "tag relations removed");
			$tag_id = 0;
		} finally {
			if ($tag_id) {
				parity_delete_tags($tag_id);
			}
		}
	}

	function test_parity_tags_merge_retargets_and_recomputes_usage() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();
		$ids = [];
		$page_ids = [];

		try {
			$a = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity Alpha"])));
			$b = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity Beta"])));
			$c = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity Gamma"])));
			$ids = [(int)$a["id"], (int)$b["id"], (int)$c["id"]];

			// Use two live pages as entry anchors (example-site has several).
			$page_ids = array_map(
				"intval",
				SQL::fetchAllSingle("SELECT id FROM bigtree_pages WHERE id > 0 ORDER BY id ASC LIMIT 2")
			);

			if (count($page_ids) < 2) {
				echo "  (skipped — need ≥2 pages for merge relation fixture)\n";

				return;
			}

			// page0 → A,B ; page1 → B  (after merge B→A: page0 has A once, page1 has A)
			SQL::insert("bigtree_tags_rel", ["table" => "bigtree_pages", "tag" => $ids[0], "entry" => (string)$page_ids[0]]);
			SQL::insert("bigtree_tags_rel", ["table" => "bigtree_pages", "tag" => $ids[1], "entry" => (string)$page_ids[0]]);
			SQL::insert("bigtree_tags_rel", ["table" => "bigtree_pages", "tag" => $ids[1], "entry" => (string)$page_ids[1]]);

			$merged = parity_data($svc->merge(parity_request(1, 1, [], [
				"into" => $ids[0],
				"from" => [$ids[1]],
			])));

			T::equals((int)$merged["id"], $ids[0], "merge returns survivor");
			T::ok(!SQL::exists("bigtree_tags", $ids[1]), "source tag deleted");
			T::ok(SQL::exists("bigtree_tags", $ids[2]), "unrelated tag untouched");

			$usage = (int)SQL::fetchSingle("SELECT usage_count FROM bigtree_tags WHERE id = ?", $ids[0]);
			$rel_count = (int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ?", $ids[0]);
			T::equals($usage, $rel_count, "usage_count matches rel rows after merge");

			// The fixture gave page0 BOTH tags, so retargeting B→A collides there.
			// A blind UPDATE left two identical rows on page0 — the tag rendered
			// twice on the front end and the usage count was permanently one high.
			// This asserts per-record uniqueness, which is what the comment above
			// the fixture always described.
			T::equals($rel_count, 2, "survivor has exactly one relation per record");

			$per_record = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ? AND `table` = 'bigtree_pages' AND entry = ?",
				$ids[0],
				(string)$page_ids[0]
			);
			T::equals($per_record, 1, "the record that carried both tags has one relation, not two");

			$ids[1] = 0; // already deleted
		} finally {
			foreach ($ids as $id) {
				if ($id) {
					parity_delete_tags($id);
				}
			}
		}
	}

	function test_parity_tags_merge_self_rejected() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();
		$tag_id = 0;

		try {
			$tag = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity Self Merge"])));
			$tag_id = (int)$tag["id"];

			T::throws(
				function () use ($svc, $tag_id) {
					$svc->merge(parity_request(1, 1, [], [
						"into" => $tag_id,
						"from" => [$tag_id],
					]));
				},
				BadRequestException::class,
				"self-merge rejected"
			);
		} finally {
			parity_delete_tags($tag_id);
		}
	}

	/**
	 * Audit #3 (B3): the AI tag-hygiene seam, against the same backend the REST
	 * merge route uses.
	 *
	 * add_tags/remove_tags manage which tags a record carries; merge and rename
	 * manage the vocabulary itself. The capability summary already implied the
	 * assistant could do this, so the model could promise a merge it couldn't do.
	 */
	function test_parity_ai_merge_tags_moves_records_and_deletes_the_source() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();
		$admin_id = parity_seed_user(["level" => 1]);
		$admin = (object)["id" => $admin_id, "level" => 1, "permissions" => []];
		$ids = [];

		try {
			$a = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity AI Keep"])));
			$b = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity AI Merge"])));
			$ids = [(int)$a["id"], (int)$b["id"]];

			$page_id = (int)SQL::fetchSingle("SELECT id FROM bigtree_pages WHERE id > 0 ORDER BY id ASC LIMIT 1");

			if ($page_id < 1) {
				echo "  (skipped — need a page for the relation fixture)\n";

				return;
			}

			SQL::insert("bigtree_tags_rel", ["table" => "bigtree_pages", "tag" => $ids[1], "entry" => (string)$page_id]);

			// Tags are addressable by name, which is what the model actually has.
			$validated = $svc->aiValidateTagMerge([
				"into" => "parity ai keep",
				"from" => ["parity ai merge"],
			], $admin);

			T::ok(!empty($validated["ok"]), "the merge validates by tag name");
			T::equals($validated["preview"]["records_moved"], 1, "the card states how many records move");
			T::ok(
				strpos((string)$validated["summary"], "can't be undone") !== false,
				"the summary says the merge is irreversible"
			);

			$result = $svc->aiMergeTags($validated["payload"], $admin);

			T::equals($result["mode"], "merged", "the merge applies");
			T::ok(!SQL::exists("bigtree_tags", $ids[1]), "the merged tag is deleted");
			T::ok(SQL::exists("bigtree_tags", $ids[0]), "the surviving tag remains");

			$moved = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_tags_rel WHERE tag = ? AND entry = ?", $ids[0], (string)$page_id
			);
			T::equals($moved, 1, "the tagged record moved to the surviving tag");
			T::equals(
				(int)SQL::fetchSingle("SELECT usage_count FROM bigtree_tags WHERE id = ?", $ids[0]),
				1,
				"usage_count was recomputed"
			);

			$ids[1] = 0;

			// Merging a tag into itself would delete it and orphan what it carried.
			$self = $svc->aiValidateTagMerge(["into" => "parity ai keep", "from" => ["parity ai keep"]], $admin);
			T::ok(isset($self["error"]), "merging a tag into itself is refused");

			$unknown = $svc->aiValidateTagMerge(["into" => "parity ai keep", "from" => ["no such tag"]], $admin);
			T::ok(isset($unknown["error"]), "an unknown source tag is a recoverable error");
		} finally {
			SQL::query("DELETE FROM bigtree_tags_rel WHERE tag IN (?, ?)", $ids[0] ?? 0, $ids[1] ?? 0);

			foreach ($ids as $id) {
				if ($id) {
					parity_delete_tags($id);
				}
			}

			parity_delete_users($admin_id);
		}
	}

	function test_parity_ai_rename_tag_refuses_a_collision() {
		if (!parity_db_available()) {
			return;
		}

		$svc = new TagService();
		$admin_id = parity_seed_user(["level" => 1]);
		$admin = (object)["id" => $admin_id, "level" => 1, "permissions" => []];
		$editor_id = parity_seed_user(["level" => 0]);
		$editor = (object)["id" => $editor_id, "level" => 0, "permissions" => []];
		$ids = [];

		try {
			$a = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity AI Rename"])));
			$b = parity_data($svc->create(parity_request(1, 1, [], ["tag" => "Parity AI Taken"])));
			$ids = [(int)$a["id"], (int)$b["id"]];

			$denied = $svc->aiValidateTagRename(["tag" => "parity ai rename", "name" => "X"], $editor);
			T::ok(isset($denied["denied"]), "an editor cannot rename tags");

			// A rename onto an existing name is a merge in disguise — refusing it
			// beats leaving two tags sharing one name.
			$collision = $svc->aiValidateTagRename([
				"tag" => "parity ai rename",
				"name" => "Parity AI Taken",
			], $admin);
			T::ok(isset($collision["error"]), "renaming onto an existing tag is refused");
			T::ok(strpos((string)$collision["error"], "merge_tags") !== false, "and points at merge_tags instead");

			$validated = $svc->aiValidateTagRename([
				"tag" => "parity ai rename",
				"name" => "Parity AI Renamed",
			], $admin);
			T::ok(!empty($validated["ok"]), "a free name validates");

			$result = $svc->aiRenameTag($validated["payload"], $admin);
			T::equals($result["mode"], "renamed", "the rename applies");

			$stored = SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?", $ids[0]);
			T::equals($stored["tag"], "parity ai renamed", "the tag was renamed (normalized)");
			T::ok((string)$stored["route"] !== "", "its public route was regenerated");
		} finally {
			foreach ($ids as $id) {
				parity_delete_tags($id);
			}

			parity_delete_users($admin_id, $editor_id);
		}
	}
