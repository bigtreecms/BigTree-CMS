<?php
	/**
	 * Audit #32: a redirect is judged by the router that will serve it.
	 *
	 * create_redirect is the one AI write whose whole job happens at request time,
	 * and its checks — "is the source a live page?", "does the destination exist?",
	 * "does this loop?" — used a bare `bigtree_pages.path = ?` that knew none of the
	 * front end's rules: not the site's trunk path, not archived/publish/expire, not
	 * route history, not the redirects already in bigtree_404s. Every case here is a
	 * redirect the router would serve differently from what the check assumed.
	 */

	use BigTree\Services\FourOhFourService;
	use BigTree\Services\PageService;
	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\Tools\AbstractMutatingTool;

	/**
	 * Run $test with a throwaway two-site config: the primary site at the root and
	 * site "b" under a fresh trunk page. Restores the config in `finally`, as
	 * LinkServiceTest does.
	 *
	 * @param callable(int $trunk_id, string $trunk_path):void $test
	 */
	function parity_redirect_with_two_sites(callable $test): void {
		global $bigtree;

		$original = $bigtree["config"]["sites"] ?? null;
		$trunk_path = parity_unique_route("zz-site-b");
		$trunk = parity_seed_page(["route" => $trunk_path, "path" => $trunk_path, "trunk" => "on"]);
		$bigtree["config"]["sites"] = [
			"primary" => [
				"name" => "Primary",
				"trunk" => 0,
				"domain" => "https://primary.zz-parity.test",
				"www_root" => "https://primary.zz-parity.test/",
			],
			"b" => [
				"name" => "Site B",
				"trunk" => $trunk,
				"domain" => "https://site-b.zz-parity.test",
				"www_root" => "https://site-b.zz-parity.test/",
			],
		];

		try {
			$test($trunk, $trunk_path);
		} finally {
			if ($original === null) {
				unset($bigtree["config"]["sites"]);
			} else {
				$bigtree["config"]["sites"] = $original;
			}

			parity_delete_page($trunk);
		}
	}

	/** Run $test on a single-site config, whatever the local install is. */
	function parity_redirect_with_one_site(callable $test): void {
		global $bigtree;

		$original = $bigtree["config"]["sites"] ?? null;
		$bigtree["config"]["sites"] = [];

		try {
			$test();
		} finally {
			if ($original === null) {
				unset($bigtree["config"]["sites"]);
			} else {
				$bigtree["config"]["sites"] = $original;
			}
		}
	}

	function parity_redirect_admin(): object {
		$id = parity_seed_user(["level" => 1]);

		return (object)["id" => $id, "level" => 1, "permissions" => []];
	}

	/** Remove every 404 row and route-history row a test made. */
	function parity_redirect_cleanup(array $sources, array $old_routes = []): void {
		foreach ($sources as $source) {
			SQL::query("DELETE FROM bigtree_404s WHERE broken_url = ?", trim($source, "/"));
		}

		foreach ($old_routes as $old_route) {
			SQL::delete("bigtree_route_history", ["old_route" => $old_route]);
		}
	}

	// — E1: the source —

	function test_a_site_b_source_is_not_refused_because_of_a_primary_site_page() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_two_sites(function (int $trunk, string $trunk_path) {
			$slug = parity_unique_route();
			$primary = parity_seed_page(["route" => $slug, "path" => $slug]);
			$admin = parity_redirect_admin();

			try {
				$validated = (new FourOhFourService())->aiValidateRedirectCreate(
					["from" => "/{$slug}", "to" => "/", "site_key" => "b"],
					$admin
				);

				T::ok(!empty($validated["ok"]), "a site-B source stages though the primary site has a page at that slug");
			} finally {
				parity_delete_page($primary);
				parity_redirect_cleanup([$slug]);
			}
		});
	}

	function test_a_site_b_source_that_is_a_live_site_b_page_is_refused() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_two_sites(function (int $trunk, string $trunk_path) {
			$slug = parity_unique_route();
			$page = parity_seed_page([
				"parent" => $trunk,
				"route" => $slug,
				"path" => "{$trunk_path}/{$slug}",
				"nav_title" => "ZZ Site B Team",
			]);
			$admin = parity_redirect_admin();

			try {
				$validated = (new FourOhFourService())->aiValidateRedirectCreate(
					["from" => "/{$slug}", "to" => "/", "site_key" => "b"],
					$admin
				);

				T::ok(isset($validated["error"]), "a redirect from a live site-B page is refused");
				T::ok(
					strpos((string)($validated["error"] ?? ""), "ZZ Site B Team") !== false
						&& strpos((string)($validated["error"] ?? ""), "“b” site") !== false,
					"naming the page that actually serves the URL, and its site"
				);
			} finally {
				parity_delete_page($page);
				parity_redirect_cleanup([$slug]);
			}
		});
	}

	function test_an_expired_page_source_stages() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_one_site(function () {
			$slug = parity_unique_route();
			$page = parity_seed_page(["route" => $slug, "path" => $slug, "expire_at" => date("Y-m-d H:i:s", time() - 86400)]);
			$admin = parity_redirect_admin();

			try {
				$validated = (new FourOhFourService())->aiValidateRedirectCreate(["from" => "/{$slug}", "to" => "/"], $admin);

				T::ok(!empty($validated["ok"]), "an expired page's URL 404s, so a redirect from it stages");
			} finally {
				parity_delete_page($page);
				parity_redirect_cleanup([$slug]);
			}
		});
	}

	function test_a_scheduled_page_source_stages_with_the_disclosure() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_one_site(function () {
			$slug = parity_unique_route();
			$page = parity_seed_page([
				"route" => $slug,
				"path" => $slug,
				"nav_title" => "ZZ Spring Launch",
				"publish_at" => date("Y-m-d H:i:s", time() + 7 * 86400),
			]);
			$admin = parity_redirect_admin();

			try {
				$validated = (new FourOhFourService())->aiValidateRedirectCreate(["from" => "/{$slug}", "to" => "/"], $admin);
				$warning = (string)($validated["preview"]["warning"] ?? "");

				T::ok(!empty($validated["ok"]), "a scheduled page's URL 404s until it goes live, so the redirect stages");
				T::ok(
					strpos($warning, "scheduled to go live") !== false && strpos($warning, "ZZ Spring Launch") !== false,
					"and the card says the page takes the URL back when it goes live"
				);
				T::ok(
					strpos((string)$validated["summary"], "scheduled to go live") !== false,
					"so does the summary the model reads"
				);
			} finally {
				parity_delete_page($page);
				parity_redirect_cleanup([$slug]);
			}
		});
	}

	function test_the_source_is_re_asked_at_approval() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_two_sites(function (int $trunk, string $trunk_path) {
			$slug = parity_unique_route();
			$admin = parity_redirect_admin();
			$page = 0;
			$svc = new FourOhFourService();

			try {
				$validated = $svc->aiValidateRedirectCreate(["from" => "/{$slug}", "to" => "/", "site_key" => "b"], $admin);
				T::ok(!empty($validated["ok"]), "the redirect stages while nothing is at the source");

				// A site-B page lands at the source inside the proposal's life.
				$page = parity_seed_page(["parent" => $trunk, "route" => $slug, "path" => "{$trunk_path}/{$slug}"]);
				$result = $svc->aiCreateRedirect($validated["payload"], $admin);

				T::equals($result["mode"], "error", "approval refuses a source that has become a live site-B page");
			} finally {
				parity_delete_page($page);
				parity_redirect_cleanup([$slug]);
			}
		});
	}

	// — E2: the destination —

	function test_a_held_destination_page_warns_with_its_reason() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_one_site(function () {
			$admin = parity_redirect_admin();
			$source = parity_unique_route();
			$cases = [
				"archived" => [["archived" => "on"], "is archived"],
				"expired" => [["expire_at" => date("Y-m-d H:i:s", time() - 86400)], "expired on"],
				"scheduled" => [["publish_at" => date("Y-m-d H:i:s", time() + 86400)], "isn't live until"],
			];
			$pages = [];

			try {
				foreach ($cases as $label => [$overrides, $phrase]) {
					$slug = parity_unique_route();
					$pages[] = parity_seed_page(array_merge(["route" => $slug, "path" => $slug], $overrides));
					$validated = (new FourOhFourService())->aiValidateRedirectCreate(
						["from" => "/{$source}", "to" => "/{$slug}"],
						$admin
					);

					T::ok(!empty($validated["ok"]), "a redirect to a {$label} page still stages");
					T::ok(
						strpos((string)($validated["preview"]["warning"] ?? ""), $phrase) !== false,
						"and warns that the {$label} page won't serve it"
					);
				}
			} finally {
				foreach ($pages as $page) {
					parity_delete_page($page);
				}

				parity_redirect_cleanup([$source]);
			}
		});
	}

	function test_a_site_b_destination_is_checked_against_site_b() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_two_sites(function (int $trunk, string $trunk_path) {
			$admin = parity_redirect_admin();
			$source = parity_unique_route();
			$slug = parity_unique_route();
			$page = parity_seed_page(["parent" => $trunk, "route" => $slug, "path" => "{$trunk_path}/{$slug}"]);

			try {
				$validated = (new FourOhFourService())->aiValidateRedirectCreate(
					["from" => "/{$source}", "to" => "/{$slug}", "site_key" => "b"],
					$admin
				);

				T::ok(!empty($validated["ok"]), "the redirect stages");
				T::ok(
					!isset($validated["preview"]["warning"]),
					"a real site-B destination carries no spurious no-page warning"
				);
			} finally {
				parity_delete_page($page);
				parity_redirect_cleanup([$source]);
			}
		});
	}

	function test_a_site_b_prior_change_matches_the_create_page_card() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_two_sites(function (int $trunk, string $trunk_path) {
			$admin = parity_redirect_admin();
			$source = parity_unique_route();
			$slug = parity_unique_route();

			try {
				$validated = (new FourOhFourService())->aiValidateRedirectCreate(
					["from" => "/{$source}", "to" => "/{$slug}", "site_key" => "b"],
					$admin
				);

				T::equals(
					(string)($validated["prior_change"]["value"] ?? ""),
					"{$trunk_path}/{$slug}",
					"the dependency names the stored, trunk-prefixed path"
				);

				// A site-B create_page card's `path` is the stored path (PageService
				// aiValidatePageCreate's preview), so the link forms end to end.
				[$store, $tool] = ai_sequencing_pair();
				$store->addPending(
					"prop-site-b-page",
					"create_page",
					"Create page “Pricing” under Site B.",
					["route" => $slug],
					["path" => "/{$trunk_path}/{$slug}"]
				);
				$tool->validation = $validated;
				$result = $tool->execute([], new AIToolContext(ai_fake_user(2), 8, "5"));

				T::equals($result->type, AIToolResult::PROPOSAL, "the redirect still stages");
				T::equals(
					$store->created[0]["payload"][AbstractMutatingTool::DEPENDS_ON_KEY]["id"] ?? null,
					"prop-site-b-page",
					"and records the site-B create_page card as its prerequisite"
				);
			} finally {
				parity_redirect_cleanup([$source]);
			}
		});
	}

	// — E3: loops, chains and route history —

	function test_a_two_hop_loop_is_refused_at_staging_and_at_approval() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_one_site(function () {
			$admin = parity_redirect_admin();
			$plans = parity_unique_route();
			$pricing = parity_unique_route();
			$svc = new FourOhFourService();

			try {
				FourOhFourService::create301("/{$plans}", "/{$pricing}");
				$refused = $svc->aiValidateRedirectCreate(["from" => "/{$pricing}", "to" => "/{$plans}"], $admin);

				T::ok(isset($refused["error"]), "reversing an existing redirect is refused");
				T::ok(
					strpos((string)($refused["error"] ?? ""), "loop forever") !== false
						&& strpos((string)($refused["error"] ?? ""), "/{$plans}") !== false,
					"in the self-loop's words, naming the existing redirect"
				);

				// The other half can be created inside the proposal's 24h life.
				parity_redirect_cleanup([$plans]);
				$validated = $svc->aiValidateRedirectCreate(["from" => "/{$pricing}", "to" => "/{$plans}"], $admin);
				T::ok(!empty($validated["ok"]), "with no reverse row it stages");

				FourOhFourService::create301("/{$plans}", "/{$pricing}");
				$result = $svc->aiCreateRedirect($validated["payload"], $admin);

				T::equals($result["mode"], "error", "and approval refuses once the reverse row exists");
			} finally {
				parity_redirect_cleanup([$plans, $pricing]);
			}
		});
	}

	function test_a_chain_is_disclosed_with_its_final_target() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_one_site(function () {
			$admin = parity_redirect_admin();
			$final = parity_unique_route();
			$page = parity_seed_page(["route" => $final, "path" => $final]);
			$middle = parity_unique_route();
			$source = parity_unique_route();

			try {
				FourOhFourService::create301("/{$middle}", "/{$final}");
				$validated = (new FourOhFourService())->aiValidateRedirectCreate(
					["from" => "/{$source}", "to" => "/{$middle}"],
					$admin
				);
				$warning = (string)($validated["preview"]["warning"] ?? "");

				T::ok(!empty($validated["ok"]), "a chain is legal, so it stages");
				T::ok(
					strpos($warning, "/{$middle} already redirects to /{$final}") !== false
						&& strpos($warning, "directly") !== false,
					"and the card names the final target so the approver can point there"
				);
				T::ok(strpos($warning, "No page exists") === false, "without claiming the destination is missing");
			} finally {
				parity_delete_page($page);
				parity_redirect_cleanup([$middle, $source]);
			}
		});
	}

	function test_an_exact_route_history_match_is_disclosed_as_replaced() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_one_site(function () {
			$admin = parity_redirect_admin();
			$new = parity_unique_route();
			$page = parity_seed_page(["route" => $new, "path" => $new]);
			$old = parity_unique_route();
			$svc = new FourOhFourService();

			try {
				SQL::insert("bigtree_route_history", ["old_route" => $old, "new_route" => $new]);
				$validated = $svc->aiValidateRedirectCreate(["from" => "/{$old}", "to" => "/"], $admin);

				T::ok(!empty($validated["ok"]), "overriding an automatic redirect is allowed");
				T::ok(
					strpos((string)$validated["preview"]["replaces"], "automatic redirect to /{$new}") !== false,
					"and the card's replaces row names the automatic redirect"
				);

				$result = $svc->aiCreateRedirect($validated["payload"], $admin);

				T::equals($result["mode"], "created", "the redirect applies");
				T::ok(
					strpos((string)($result["note"] ?? ""), "automatic redirect to /{$new}") !== false,
					"and the result says which automatic redirect it replaced"
				);
			} finally {
				parity_delete_page($page);
				parity_redirect_cleanup([$old], [$old]);
			}
		});
	}

	function test_a_source_under_a_routed_old_route_warns_it_will_never_fire() {
		if (!parity_db_available()) {
			return;
		}

		parity_redirect_with_one_site(function () {
			$admin = parity_redirect_admin();
			$template = "zz_parity_routed_" . bin2hex(random_bytes(3));
			parity_seed_template($template);
			BigTreeJSONDB::update("templates", $template, ["routed" => "on"]);
			$new = parity_unique_route();
			$page = parity_seed_page(["route" => $new, "path" => $new, "template" => $template]);
			$old = parity_unique_route();

			try {
				SQL::insert("bigtree_route_history", ["old_route" => $old, "new_route" => $new]);
				$validated = (new FourOhFourService())->aiValidateRedirectCreate(
					["from" => "/{$old}/member", "to" => "/"],
					$admin
				);

				T::ok(!empty($validated["ok"]), "the redirect stages — the template could stop being routed");
				T::ok(
					strpos((string)($validated["preview"]["warning"] ?? ""), "never fire") !== false,
					"but the card warns the old route's prefix walk catches the URL first"
				);
			} finally {
				parity_delete_page($page);
				parity_delete_template($template);
				parity_redirect_cleanup(["{$old}/member"], [$old]);
			}
		});
	}

	// — E5: archive_page counts the redirects that point at the page —

	function test_archiving_a_page_counts_the_redirects_that_point_at_it() {
		if (!parity_db_available()) {
			return;
		}

		$target = parity_seed_page();
		$quiet = parity_seed_page();
		$sources = [parity_unique_route(), parity_unique_route()];

		try {
			// Both slash encodings, the way the inbound page-link counter matches them.
			SQL::insert("bigtree_404s", [
				"broken_url" => $sources[0],
				"get_vars" => "",
				"redirect_url" => "ipl://{$target}//W10=//",
			]);
			SQL::insert("bigtree_404s", [
				"broken_url" => $sources[1],
				"get_vars" => "",
				"redirect_url" => "ipl:\\/\\/{$target}\\/\\/W10=\\/\\/",
			]);

			$developer = (object)["id" => 0, "level" => 2, "permissions" => []];
			$validated = (new PageService())->aiValidatePageArchive(["id" => $target], $developer);

			T::ok(
				strpos((string)($validated["preview"]["warning"] ?? ""), "2 redirects point here") !== false,
				"the archive card counts the redirects that will land on a 404, in both encodings"
			);

			$none = (new PageService())->aiValidatePageArchive(["id" => $quiet], $developer);
			T::ok(!isset($none["preview"]["warning"]), "a page no redirect points at carries no warning");
		} finally {
			parity_redirect_cleanup($sources);
			parity_delete_page($quiet);
			parity_delete_page($target);
		}
	}
