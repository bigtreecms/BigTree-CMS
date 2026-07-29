<?php
	/**
	 * Audit #12 B1 and guard E7: a writable field type whose candidate rows are
	 * discoverable, and error text that names tools which exist.
	 *
	 * Audit #11 B2 made `one-to-many` and `many-to-many` settable by entry id, and
	 * did it carefully — RelationDomain builds the `__mtm__` descriptor from the field
	 * definition rather than the model, checks every id against the target table,
	 * applies per-row permissions and honours the field's `max`. To write one, the
	 * model needs ids, and nothing in the catalogue could produce them: a relation's
	 * target is `settings["table"]` / `settings["mtm-other-table"]`, which is not
	 * required to be any module's table and usually isn't one. RelationDomain's own
	 * error told the model to "find them with list_module_entries", a tool that cannot
	 * reach that table — and the route-family contract recorded the same non-existent
	 * capability as covering the endpoint.
	 *
	 * E7 is the general form of that mistake: a message naming a tool is a promise,
	 * and a promise about a tool that isn't registered is worse than no promise. This
	 * asserts every tool name any AI-facing message mentions is one the registry
	 * actually has.
	 */

	use BigTree\Services\AI\RelationDomain;
	use BigTree\Services\AutoModuleService;

	/**
	 * Identifiers that read like a tool name but aren't one. Short and justified —
	 * each is a real name from somewhere else in the system that happens to share the
	 * verb-prefixed shape tool names use.
	 *
	 * @return array<string,string>
	 */
	function ai_message_tool_name_exceptions(): array {

		return [
			"publish_at" => "a page tool's own argument, named in prose that explains scheduling",
			"get_vars" => "a bigtree_404s column, named in a SQL string rather than a message",
			"save_as_draft" => "the argument every content tool declares for queueing rather than publishing",
		];
	}

	/**
	 * Tool-name-shaped tokens in the string literals of the AI services and domains.
	 *
	 * @return array<string,list<string>> token => the files that mention it
	 */
	function ai_message_tool_mentions(): array {
		$files = array_merge(
			glob(SERVER_ROOT . "core/inc/bigtree/services/*.php") ?: [],
			glob(SERVER_ROOT . "core/inc/bigtree/services/AI/*.php") ?: [],
			glob(SERVER_ROOT . "core/inc/bigtree/services/AI/Tools/*.php") ?: []
		);
		$verbs = "get|list|search|create|update|delete|add|remove|merge|rename|archive|unarchive|move|publish"
			. "|reject|save|restore|set|scaffold|semantic";
		$found = [];

		foreach ($files as $file) {
			$source = (string)file_get_contents($file);
			preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $source, $literals);

			foreach ($literals[1] as $literal) {
				// Only prose is a promise. An array key, an error code and a field
				// setting's name all share the shape and none of them is text anyone
				// reads, so the scan is scoped to literals with words in them.
				if (strpos($literal, " ") === false) {

					continue;
				}

				// Interpolated variables carry the same shape as a tool name and are not
				// text anyone reads either — strip them before looking.
				$literal = (string)preg_replace('/\{\$[^}]*\}/', "", $literal);
				preg_match_all('/\b(?:' . $verbs . ')_[a-z_]+\b/', $literal, $tokens);

				foreach ($tokens[0] as $token) {
					$found[$token][] = basename($file);
				}
			}
		}

		return $found;
	}

	/** E7: every tool an AI-facing message names is a tool the registry has. */
	function test_every_tool_named_in_a_message_is_registered() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$names = [];

		foreach ($registry->availableTools($developer) as $tool) {
			$names[] = $tool->name();
		}

		// The search registry's tools are registered separately at build time.
		foreach (["get_page", "get_module_entry", "search_tags", "search_users", "search_modules"] as $name) {
			$names[] = $name;
		}

		$exceptions = ai_message_tool_name_exceptions();
		$mentions = ai_message_tool_mentions();
		$phantom = [];

		T::ok(count($mentions) > 20, "tool mentions were found to check");

		foreach ($mentions as $token => $files) {
			if (in_array($token, $names, true) || isset($exceptions[$token])) {

				continue;
			}

			$phantom[] = "{$token} (" . implode(", ", array_unique($files)) . ")";
		}

		T::equals(
			implode(", ", $phantom),
			"",
			"no message names a tool that doesn't exist"
		);
	}

	/** No exception may outlive the reason it was written for. */
	function test_tool_name_exceptions_are_still_mentioned() {
		$mentions = ai_message_tool_mentions();
		$stale = array_values(array_diff(array_keys(ai_message_tool_name_exceptions()), array_keys($mentions)));

		// save_as_draft is exempted pre-emptively (it is the one argument name most
		// likely to be written into prose), so it is allowed to be absent.
		$stale = array_values(array_diff($stale, ["save_as_draft"]));

		T::equals(implode(", ", $stale), "", "no tool-name exception names a token nothing mentions");
	}

	/** The specific promise B1 makes: the relation refusal points somewhere real. */
	function test_relation_refusals_point_at_the_discovery_tool() {
		$registry = ai_wiring_registry();
		T::ok($registry->get("get_relation_options") !== null, "get_relation_options is a registered tool");
		T::equals($registry->get("get_relation_options")->kind(), "read", "and it is a read tool");

		$field = ["title" => "Regions", "column" => "regions", "settings" => ["table" => "bigtree_pages"]];
		$prose = RelationDomain::resolveOneToMany($field, ["the northern one"], ai_wiring_user(2));

		T::ok(
			strpos((string)($prose["error"] ?? ""), "get_relation_options") !== false,
			"a relation named in prose is pointed at the tool that lists its rows"
		);
		T::ok(
			strpos((string)($prose["error"] ?? ""), "list_module_entries") === false,
			"and no longer at one that cannot reach the field's table"
		);
	}

	/**
	 * The seam itself, against a throwaway lookup table that belongs to no module —
	 * which is the case the capability existed for and could not serve.
	 */
	function test_relation_options_list_rows_from_the_fields_own_table() {
		try {
			SQL::fetchSingle("SELECT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable: " . $e->getMessage() . ")\n";

			return;
		}

		$table = "zz_relation_probe_" . substr(md5((string)mt_rand()), 0, 8);
		$module_id = null;

		try {
			SQL::query("CREATE TABLE `{$table}` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(191), PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
			SQL::query("INSERT INTO `{$table}` (`name`) VALUES ('Northern'), ('Southern'), ('Eastern')");

			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ Relation Probe " . uniqid(),
				"route" => "zz-relation-probe-" . uniqid(),
				"forms" => [[
					"id" => "form-1",
					"title" => "Entry",
					// Deliberately a *different* table from the relation's target: the
					// module owns `bigtree_pages`-shaped entries, the relation points at a
					// lookup table no module owns.
					"table" => "bigtree_pages",
					"fields" => [[
						"column" => "regions",
						"type" => "one-to-many",
						"title" => "Regions",
						"settings" => ["table" => $table, "title_column" => "name"],
					]],
				]],
			]);
			BigTreeJSONDB::$Cache = [];

			$service = new AutoModuleService();
			$result = $service->aiRelationOptions($module_id, "", "regions", "", 25, 0, ai_wiring_user(2));

			T::ok(!isset($result["error"]), "the lookup succeeded (" . (string)($result["error"] ?? "") . ")");
			T::equals(count($result["options"] ?? []), 3, "every row in the field's target table is offered");
			T::equals((string)($result["relation_type"] ?? ""), "one-to-many", "the relation type is reported");
			T::ok(
				isset($result["options"][0]["id"], $result["options"][0]["title"]),
				"each option carries the id a write takes and a title to choose it by"
			);

			$filtered = $service->aiRelationOptions($module_id, "", "regions", "North", 25, 0, ai_wiring_user(2));
			T::equals(count($filtered["options"] ?? []), 1, "a query filters before the cap rather than after");

			$capped = $service->aiRelationOptions($module_id, "", "regions", "", 2, 0, ai_wiring_user(2));
			T::equals(count($capped["options"] ?? []), 2, "the window is bounded");
			T::equals($capped["has_more"] ?? null, true, "and says so rather than implying it showed everything");

			// The window pages, so `has_more` is a fact about the table rather than
			// about the page size — and the last page says so.
			$paged = $service->aiRelationOptions($module_id, "", "regions", "", 2, 2, ai_wiring_user(2));
			T::equals(count($paged["options"] ?? []), 1, "the next page is the rest of the table");
			T::equals($paged["has_more"] ?? null, false, "and the last page doesn't claim there's more");
			T::equals(
				(string)($capped["options"][0]["id"] ?? "") === (string)($paged["options"][0]["id"] ?? ""),
				false,
				"a paged window doesn't hand back the rows the first one did"
			);

			// A column that isn't a relation, and one that isn't there at all, are
			// recoverable errors naming where to look — not exceptions.
			$wrong = $service->aiRelationOptions($module_id, "", "nope", "", 25, 0, ai_wiring_user(2));
			T::ok(isset($wrong["error"]), "an unknown column is refused");
			T::ok(
				strpos((string)$wrong["error"], "get_module_schema") !== false,
				"and the refusal names the tool that lists the real ones"
			);
		} finally {
			if ($module_id !== null) {
				BigTreeJSONDB::delete("modules", $module_id);
			}

			if (BigTree::tableExists($table)) {
				SQL::query("DROP TABLE `{$table}`");
			}
		}
	}
