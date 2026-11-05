<?php
	/**
	 * `bigtree_pages.external` behaves like a link again.
	 *
	 * Legacy makeIPLs the column on both write paths (admin.php:1637 createPage,
	 * :8837 updatePage); no writer in the remaster did. Two things followed, and both
	 * are asserted here end to end rather than by reading the source:
	 *
	 *  - **E2, the round trip.** The read handed the model a stored `ipl://…` blob and
	 *    the write gate refused it back ("isn't a link I can store. Use a full URL
	 *    starting with http:// or https://"), so the only compliant answer to the
	 *    error downgraded a durable internal link to a hard URL that breaks the next
	 *    time the target page moves. The failing step was *validate*, not storage,
	 *    which is why this drives the real seams in order.
	 *  - **E3, the allocation leg.** performCreate has always passed `external` into
	 *    allocatePageResources, precisely because legacy's makeIPL turns a link at a
	 *    library file into `irl://<id>//<prefix>` — so with nothing producing one,
	 *    that argument was dead code and a page whose nav link pointed at a PDF held no
	 *    allocation row. Deleting the file then warned about nothing.
	 */

	use BigTree\Services\PageService;
	use BigTree\Services\ResourceService;
	use BigTree\Services\ResourceAllocationService;

	/** A publisher who can write anywhere in the tree. */
	function parity_link_publisher(): array {
		$id = parity_seed_user(["level" => 1, "permissions" => ["page" => [0 => "p"]]]);

		return [$id, (object)["id" => $id, "level" => 1, "permissions" => ["page" => [0 => "p"]]]];
	}

	/** The stored token form of a link at a live page, exactly as makeIPL writes it. */
	function parity_link_page_token(int $page_id): string {

		return "ipl://" . $page_id . "//" . base64_encode(json_encode([])) . "////";
	}

	/**
	 * A throwaway file in the Files library, plus the absolute URL that points at it.
	 *
	 * The lookup behind makeIPL memoizes by file and by URL and can be primed whole,
	 * so a row inserted mid-suite is invisible to it until the caches are dropped —
	 * hence the reflection reset, which is about the fixture being seen at all rather
	 * than about what is being tested.
	 *
	 * @return array{0:int,1:string}
	 */
	function parity_link_library_file(): array {
		$name = "zz-parity-link-" . bin2hex(random_bytes(4)) . ".pdf";
		$id = (int)SQL::insert("bigtree_resources", [
			"file" => "{staticroot}files/resources/" . $name,
			"name" => "ZZ Parity Link Fixture",
			"date" => "NOW()",
			"metadata" => "",
			"crops" => "",
			"thumbs" => "",
		]);

		parity_link_reset_resource_caches();

		return [$id, STATIC_ROOT . "files/resources/" . $name];
	}

	function parity_link_reset_resource_caches(): void {
		$class = new ReflectionClass(ResourceAllocationService::class);

		foreach (["ResourceByFileCache" => [], "ResourceByUrlCache" => [], "ResourceFileCachePrimed" => false] as $name => $empty) {
			if (!$class->hasProperty($name)) {

				continue;
			}

			$property = $class->getProperty($name);
			$property->setAccessible(true);
			$property->setValue(null, $empty);
		}
	}

	function parity_link_delete_resource(int $resource_id): void {
		if ($resource_id < 1) {

			return;
		}

		SQL::query("DELETE FROM bigtree_resource_allocation WHERE resource = ?", $resource_id);
		SQL::delete("bigtree_resources", $resource_id);
		parity_link_reset_resource_caches();
	}

	function parity_link_allocation_exists(string $entry, int $resource_id): bool {

		return (int)SQL::fetchSingle(
			"SELECT COUNT(*) FROM bigtree_resource_allocation WHERE `table` = 'bigtree_pages' AND entry = ? AND resource = ?",
			$entry,
			$resource_id
		) > 0;
	}

	/** E2: read a stored token, hand it straight back, and lose nothing. */
	function test_parity_a_stored_external_link_survives_a_read_and_a_write() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[, $publisher] = parity_link_publisher();
		$target_id = 0;
		$linking_id = 0;

		try {
			$target_id = parity_seed_page(["nav_title" => "ZZ Link Target"]);
			$token = parity_link_page_token($target_id);
			$linking_id = parity_seed_page([
				"nav_title" => "ZZ Link Source",
				"external" => $token,
				"template" => "",
			]);

			// The read leg. get_page used to hand back the raw base64 token.
			$target = $svc->aiResolvePageTarget((string)$linking_id);
			$fields = $svc->aiPageDetailFields($target);
			$read = (string)$fields["external"];

			T::ok(strpos($read, "ipl://") !== 0, "get_page returns a resolvable URL, not the stored token");
			T::ok(strpos($read, "http") === 0, "and it is an absolute URL the model can reason about");

			// The write leg: the value just read, handed straight back alongside a real
			// edit. This is the step that used to fail.
			$validated = $svc->aiValidatePageUpdate([
				"id" => (string)$linking_id,
				"external" => $read,
				"nav_title" => "ZZ Link Source Renamed",
			], $publisher);

			T::ok(
				!isset($validated["error"]) && !isset($validated["denied"]),
				"update_page accepts the link it was just shown"
					. (isset($validated["error"]) ? " (got: " . $validated["error"] . ")" : "")
			);
			T::ok(
				!array_key_exists("external", (array)($validated["payload"]["changes"] ?? [])),
				"and reports no change to it, because restating a link is not editing one"
			);

			$applied = $svc->aiUpdatePage($validated["payload"], $publisher);
			T::equals((string)($applied["mode"] ?? ""), "published", "the edit publishes");

			$stored = (string)SQL::fetchSingle("SELECT `external` FROM bigtree_pages WHERE id = ?", $linking_id);
			T::equals($stored, $token, "and the internal link is stored exactly as it was — the round trip is lossless");
		} finally {
			parity_delete_page($linking_id);
			parity_delete_page($target_id);
			parity_cleanup_tracked();
		}
	}

	/** E2, second half: a hard URL at a page on this site is stored as a token. */
	function test_parity_an_external_link_at_this_site_is_stored_as_a_token() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[$publisher_id] = parity_link_publisher();
		$target_id = 0;
		$linking_id = 0;

		try {
			$target_id = parity_seed_page(["nav_title" => "ZZ Token Target"]);
			$path = (string)SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $target_id);
			$linking_id = parity_seed_page(["nav_title" => "ZZ Token Source", "template" => ""]);

			$request = parity_request($publisher_id, 1, ["id" => $linking_id], [
				"external" => WWW_ROOT . $path . "/",
				"publish" => true,
			], [], ["page" => [0 => "p"]]);

			$svc->update($request);

			$stored = (string)SQL::fetchSingle("SELECT `external` FROM bigtree_pages WHERE id = ?", $linking_id);
			T::equals(
				$stored,
				parity_link_page_token($target_id),
				"REST's own writer tokenizes it, so the link survives the target page being moved"
			);
		} finally {
			parity_delete_page($linking_id);
			parity_delete_page($target_id);
			parity_cleanup_tracked();
		}
	}

	/**
	 * A change queued before the writers tokenized is tokenized when it publishes.
	 *
	 * publishPendingChange replays the stored blob through performUpdate, so a draft
	 * carrying a raw same-site URL — one staged before the writers tokenized, or
	 * written by any client that reached the queue another way — lands tokenized rather
	 * than raw. That is the direction we want, and it is asserted rather than assumed
	 * because the alternative (a blob replayed verbatim) is what the same shape of code
	 * once did for `resources`.
	 */
	function test_parity_a_queued_external_link_is_tokenized_when_it_publishes() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[, $publisher] = parity_link_publisher();
		$target_id = 0;
		$linking_id = 0;
		$change_id = 0;

		try {
			$target_id = parity_seed_page(["nav_title" => "ZZ Publish Target"]);
			$path = (string)SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE id = ?", $target_id);
			$linking_id = parity_seed_page(["nav_title" => "ZZ Publish Source", "template" => ""]);

			$change_id = (int)SQL::insert("bigtree_pending_changes", [
				"user" => (int)$publisher->id,
				"date" => "NOW()",
				"table" => "bigtree_pages",
				"item_id" => $linking_id,
				"changes" => json_encode(["external" => WWW_ROOT . $path . "/"]),
				"mtm_changes" => "[]",
				"tags_changes" => "[]",
				"open_graph_changes" => "[]",
				"module" => "",
				"type" => "EDIT",
				"title" => "Page Change Pending",
				"pending_page_parent" => 0,
			]);
			parity_track_pending($change_id);

			$svc->publishPendingChange(
				SQL::fetch("SELECT * FROM bigtree_pending_changes WHERE id = ?", $change_id),
				$publisher
			);

			T::equals(
				(string)SQL::fetchSingle("SELECT `external` FROM bigtree_pages WHERE id = ?", $linking_id),
				parity_link_page_token($target_id),
				"the replay through performUpdate stores the token, not the raw URL the draft held"
			);
		} finally {
			parity_delete_pending($change_id);
			parity_delete_page($linking_id);
			parity_delete_page($target_id);
			parity_cleanup_tracked();
		}
	}

	/** E3: a nav link at a library file holds an allocation row, on both surfaces. */
	function test_parity_an_external_link_at_a_library_file_is_allocated() {
		if (!parity_db_available()) {

			return;
		}

		$svc = new PageService();
		[$publisher_id, $publisher] = parity_link_publisher();
		$resource_id = 0;
		$rest_page_id = 0;
		$ai_page_id = 0;

		try {
			[$resource_id, $url] = parity_link_library_file();

			// REST.
			$request = parity_request($publisher_id, 1, [], [
				"parent" => 0,
				"nav_title" => "ZZ Allocation REST",
				"route" => parity_unique_route(),
				"external" => $url,
				"template" => "",
				"publish" => true,
			], [], ["page" => [0 => "p"]]);
			$created = parity_data($svc->create($request));
			$rest_page_id = (int)$created["id"];
			parity_track("pages", $rest_page_id);

			T::ok(
				strpos((string)SQL::fetchSingle("SELECT `external` FROM bigtree_pages WHERE id = ?", $rest_page_id), "irl://") === 0,
				"a REST-created link at a library file is stored as a resource link"
			);
			T::ok(
				parity_link_allocation_exists((string)$rest_page_id, $resource_id),
				"and the page holds an allocation row against that file"
			);

			// The assistant.
			$validated = $svc->aiValidatePageCreate([
				"parent" => 0,
				"nav_title" => "ZZ Allocation AI",
				"route" => parity_unique_route(),
				"external" => $url,
			], $publisher);

			T::ok(!isset($validated["error"]), "create_page stages a link at a library file");

			$applied = $svc->aiCreatePage($validated["payload"], $publisher);
			$ai_page_id = (int)($applied["page_id"] ?? 0);
			parity_track("pages", $ai_page_id);

			T::ok($ai_page_id > 0, "and publishes it");
			T::ok(
				parity_link_allocation_exists((string)$ai_page_id, $resource_id),
				"the AI-created page holds the same allocation row — performCreate's `external` argument is live again"
			);

			// The point of the allocation: the delete screen can say what breaks.
			$usage = parity_data((new ResourceService())->usage(
				parity_request($publisher_id, 1, ["id" => $resource_id], [], [], ["page" => [0 => "p"], "resources" => []])
			));
			$used_by = array_map(function ($row) {

				return (string)($row["link"]["entry"] ?? "");
			}, is_array($usage) ? $usage : []);

			T::ok(
				in_array((string)$ai_page_id, $used_by, true),
				"and deleting the file reports the page that links to it"
			);
		} finally {
			parity_delete_page($rest_page_id);
			parity_delete_page($ai_page_id);
			parity_link_delete_resource($resource_id);
			parity_cleanup_tracked();
		}
	}
