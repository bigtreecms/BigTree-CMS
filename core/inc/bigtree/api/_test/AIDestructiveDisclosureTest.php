<?php
	/**
	 * Audit #17 Part C: the two questions on the far side of a completed write.
	 *
	 * Audits #1–#16 all walked outward from the value to the record being written —
	 * the row, the container, the sibling proposal, the column, the clock, the
	 * caller, the card, the read seam, the audit row. Neither of the questions that
	 * only exist *after* the write had a guard anywhere:
	 *
	 *  1. What else in the CMS was pointing at the record the assistant just
	 *     destroyed or hid? Every comparable card counts its blast radius — the
	 *     template diff counts pages, the callout diff counts pages, merge_tags
	 *     counts the relations it moves — and the two entry tools counted nothing.
	 *     AIProposalPreviewContractTest cannot see that: it asserts every *emitted*
	 *     key is classified, and it structurally cannot fail on a key that was never
	 *     emitted.
	 *  2. Did the write actually land? Answered everywhere else in the stack (a false
	 *     from createItem is `mode: error`, failed scaffold DDL is a FAILED card,
	 *     TemplateScaffold refuses an unwritable directory) and nowhere in the JSON
	 *     store, which is where every template, callout, module and group lives.
	 *
	 * E1 and E2 guard the first, E3 the second. Membership in E1 is *discovered* from
	 * the seams' own source rather than listed, so a ninth destructive tool fails
	 * until somebody classifies it.
	 */

	use BigTree\Services\AI\ConfigurationStore;

	/** The source of one method, with the one-level `$this->ai…()` helpers it calls. */
	function ai17_seam_source(string $class, string $method, array $extra = []): string {
		$source = ai_surface_method_body($class, $method);

		if ($source === "") {

			return "";
		}

		preg_match_all('/\$this->([a-zA-Z0-9_]+)\(/', $source, $calls);

		foreach (array_unique(array_merge($calls[1], $extra)) as $call) {
			if ($call === $method || !method_exists($class, $call)) {

				continue;
			}

			$source .= "\n" . ai_surface_method_body($class, $call);
		}

		return $source;
	}

	/**
	 * Every proposal tool's approval seam, read out of AIChatService::executeProposal
	 * rather than listed here — the dispatch switch is the definitive statement of
	 * which service performs which tool's write.
	 *
	 * @return array<string,array{0:string,1:string}> tool => [class, method]
	 */
	function ai17_approval_seams(): array {
		$dispatch = ai_surface_method_body(\BigTree\Services\AIChatService::class, "executeProposal");
		$seams = [];

		preg_match_all(
			'/case\s+"([a-z_]+)":\s*\n\s*return\s+\(new\s+([A-Za-z0-9_]+)\(\)\)->(ai[A-Za-z0-9_]+)\(/',
			$dispatch,
			$matches,
			PREG_SET_ORDER
		);

		foreach ($matches as $match) {
			$seams[$match[1]] = ["BigTree\\Services\\" . $match[2], $match[3]];
		}

		return $seams;
	}

	/**
	 * Every tool's validate seam, read out of the tool classes themselves.
	 *
	 * @return array<string,string> tool => method
	 */
	function ai17_validate_seams(): array {
		$seams = [];

		foreach (glob(dirname(__DIR__, 2) . "/services/AI/Tools/*Tool.php") ?: [] as $file) {
			$source = (string)file_get_contents($file);

			if (!preg_match('/function name\(\): string \{\s*\n\s*return "([a-z_]+)"/', $source, $name)) {

				continue;
			}

			if (!preg_match('/backend->(aiValidate[A-Za-z0-9_]+)\(/', $source, $validate)) {

				continue;
			}

			$seams[$name[1]] = $validate[1];
		}

		return $seams;
	}

	/**
	 * What makes a tool destructive, in its own source. A tool qualifies if its card
	 * says so or if its write reaches a primitive that removes or hides a record —
	 * discovered rather than listed, because a list is exactly what audit #17 A1 and
	 * A2 slipped past (nobody had written one).
	 *
	 * @return array<string,string> substring => what it means
	 */
	function ai17_destructive_primitives(): array {

		return [
			'"destructive" => true' => "the card carries the permanent-deletion banner",
			"deleteItem(" => "deletes a live entry",
			"deletePendingItem(" => "deletes a queued change",
			"SQL::delete(" => "deletes a row",
			"BigTreeJSONDB::delete(" => "deletes a stored record",
			"DELETE FROM" => "deletes rows",
			'"archived"' => "turns on (or reads) the flag that hides a record from the site",
		];
	}

	/**
	 * The tools whose write destroys or hides a record, with why, discovered from the
	 * validate and approval seams' source.
	 *
	 * @return array<string,string> tool => the reasons it qualified
	 */
	function ai17_destructive_tools(): array {
		$validate = ai17_validate_seams();
		$found = [];

		foreach (ai17_approval_seams() as $tool => [$class, $method]) {
			// The two seams' own bodies, deliberately without the helpers they call:
			// every page write routes through the same shared performUpdate, so
			// following one level down makes half the catalogue look like it deletes
			// something. What a *tool* does is what its own two methods do.
			$source = ai_surface_method_body($class, $method);

			if (isset($validate[$tool])) {
				$source .= "\n" . ai_surface_method_body($class, $validate[$tool]);
			}

			$reasons = [];

			foreach (ai17_destructive_primitives() as $needle => $reason) {
				if (strpos($source, $needle) !== false) {
					$reasons[] = $reason;
				}
			}

			if ($reasons) {
				$found[$tool] = implode("; ", $reasons);
			}
		}

		ksort($found);

		return $found;
	}

	/**
	 * Every destructive tool → the preview key that carries its blast radius, or
	 * `exempt: <reason>`.
	 *
	 * The map may name a tool the discovery above doesn't find (update_template and
	 * update_callout destroy *fields* rather than records, and counted their reach
	 * long before this guard existed) — but it must name every tool it does find.
	 * That is the whole point: a ninth destructive tool fails this test until
	 * somebody decides which of the two it is.
	 *
	 * A value of `a/b` is a row nested inside preview key `a` — the two field diffs
	 * carry their counts as rows of the `changes` map rather than as keys of their
	 * own — so `a` is the key the card contract classifies and `b` is what has to be
	 * emitted.
	 *
	 * @return array<string,string> tool => preview key | "key/row" | "exempt: reason"
	 */
	function ai17_blast_radius(): array {

		return [
			"delete_module_entry" => "references",
			"set_module_entry_flag" => "references",
			"archive_page" => "warning",
			"update_template" => "changes/pages_using_template",
			"update_callout" => "changes/pages_using_callout",
			"merge_tags" => "records_moved",
			"unarchive_page" => "exempt: puts a hidden page back on the site — it is the repair for what "
				. "archive_page discloses, and creates no dangling reference of its own",
			"reject_pending_change" => "exempt: the only record it destroys is the queued change itself, and "
				. "nothing in the CMS can hold a reference to an unpublished change — the card diffs the change "
				. "in full",
			"remove_tags" => "exempt: removes a tag relation rather than either record it joins; the card names "
				. "the tags being removed and both records survive",
			"scaffold_module" => "exempt: destructive only in that a created table cannot be un-created — it "
				. "destroys nothing that already exists, so there is nothing pointing at it to count",
		];
	}

	/**
	 * E1: the destructive-disclosure contract.
	 *
	 * The outbound analogue of AIApprovalReferentialTest, which is explicitly the
	 * create side (tool → approval seam → the container it re-reads). Nothing bound
	 * "this write destroys or hides a record" to "the card says what pointed at it",
	 * which is why A1 and A2 sat in plain sight.
	 */
	function test_every_destructive_tool_discloses_its_blast_radius() {
		$discovered = ai17_destructive_tools();
		$declared = ai17_blast_radius();
		$validate = ai17_validate_seams();
		$approval = ai17_approval_seams();

		T::ok(count($discovered) >= 8, "the destructive-tool scan found the catalogue's destructive tools ("
			. count($discovered) . ")");

		$unclassified = [];

		foreach ($discovered as $tool => $reason) {
			if (!isset($declared[$tool])) {
				$unclassified[] = "{$tool} ({$reason})";
			}
		}

		T::equals(
			implode(", ", $unclassified),
			"",
			"every tool whose write destroys or hides a record declares the preview key carrying its blast radius"
		);

		// And the map can't keep naming a tool that no longer exists.
		$gone = array_values(array_diff(array_keys($declared), array_keys($approval)));
		T::equals(implode(", ", $gone), "", "and every classification names a tool the registry still dispatches");

		$missing = [];
		$unshaped = [];

		foreach ($declared as $tool => $key) {
			if (strpos($key, "exempt:") === 0) {

				continue;
			}

			$method = $validate[$tool] ?? "";
			$class = $approval[$tool][0] ?? "";
			// Expanded one level here — a nested count is built by the diff helper the
			// validate seam calls, not by the seam itself.
			$source = ($method !== "" && $class !== "") ? ai17_seam_source($class, $method) : "";
			$parts = explode("/", $key);
			$emitted = $parts[count($parts) - 1];

			if (strpos($source, '"' . $emitted . '"') === false) {
				$missing[] = "{$tool} (claims to emit `{$emitted}`, its validate seam never does)";
			}

			// The key has to reach the card too, which is the other half of this
			// contract and the map audit #15 built to hold it.
			if (function_exists("ai_preview_key_shapes") && !isset(ai_preview_key_shapes()[$parts[0]])) {
				$unshaped[] = "{$tool} → {$parts[0]}";
			}
		}

		T::equals(implode(", ", $missing), "", "every declared blast-radius key is really emitted by its validate seam");
		T::equals(
			implode(", ", $unshaped),
			"",
			"and every declared blast-radius key is classified in the preview ↔ card contract"
		);
	}

	/**
	 * Every table that can hold a reference to a module entry → the substring of
	 * BigTreeAutoModule::deleteItem that proves the teardown addresses it.
	 *
	 * Six of the seven were already right, which is the point: the pattern was
	 * established (audit #7 B3 deliberately moved entry teardown *into* deleteItem),
	 * and the connecting tables a module's own forms declare were the one thing left
	 * out of it — silently, because nothing enumerated the set.
	 *
	 * @return array<string,string> what holds the reference => the proof
	 */
	function ai17_entry_teardown(): array {

		return [
			"the entry's own row" => 'SQL::delete($table, $id)',
			"bigtree_resource_allocation" => 'SQL::delete("bigtree_resource_allocation"',
			"bigtree_tags_rel" => "DELETE FROM bigtree_tags_rel",
			"bigtree_open_graph" => "DELETE FROM bigtree_open_graph",
			"bigtree_pending_changes" => 'SQL::delete("bigtree_pending_changes"',
			"the module view cache" => "self::uncacheItem(",
			"the vector index" => "EmbeddingService::deleteModuleEntry(",
			"the mtm connecting tables the module's forms declare" => "self::getManyToManyRelationships(",
		];
	}

	/** E2: the entry-teardown contract, at the source. */
	function test_entry_teardown_addresses_every_table_that_references_an_entry() {
		$body = ai_surface_method_body(\BigTreeAutoModule::class, "deleteItem");
		T::ok($body !== "", "deleteItem's source was read");

		$missing = [];

		foreach (ai17_entry_teardown() as $holder => $proof) {
			if (strpos($body, $proof) === false) {
				$missing[] = "{$holder} (expected `{$proof}`)";
			}
		}

		T::equals(implode(", ", $missing), "", "deleteItem tears down every table that can reference the entry");

		// The identifiers reach raw SQL, so the descriptor helper gates them the way
		// AutoModuleService::validateMtm gates the ones arriving from a request.
		$descriptors = ai_surface_method_body(\BigTreeAutoModule::class, "getManyToManyRelationships");
		T::ok(
			strpos($descriptors, "'/^[A-Za-z0-9_]+$/'") !== false,
			"the connecting-table descriptors are validated as bare identifiers before they are interpolated"
		);

		// D1: the entry's own side is torn down, the other side is left alone. Deleting
		// rows where the entry is the `other-id` would rewrite records the approver
		// never saw, on a card that described one deletion.
		T::ok(
			strpos($body, '$relationship["my-id"]') !== false,
			"deleteItem deletes the connecting rows the entry owned"
		);
		T::ok(
			strpos($body, '$relationship["other-id"]') === false,
			"and never rewrites another entry's relation, which is disclosed on the card instead"
		);

		// The disclosure half: the delete card names what is left pointing at nothing.
		$validate = ai_surface_method_body(\BigTree\Services\AutoModuleService::class, "aiValidateEntryDelete");
		T::ok(
			strpos($validate, "aiEntryReferenceUsage(") !== false,
			"the delete card counts what still points at the entry"
		);
	}

	/** E2, behaviourally: a deleted entry leaves no rows in its connecting table. */
	function test_deleting_an_entry_clears_the_connecting_rows_it_owned() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$suffix = substr(md5(uniqid("", true)), 0, 8);
		$table = "zz_audit17_{$suffix}";
		$other = "zz_audit17_other_{$suffix}";
		$connecting = "zz_audit17_rel_{$suffix}";
		$module_id = null;

		try {
			SQL::query("CREATE TABLE `{$table}` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, "
				. "`title` VARCHAR(191) NOT NULL DEFAULT '', PRIMARY KEY (`id`)) ENGINE=InnoDB "
				. "DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
			SQL::query("CREATE TABLE `{$other}` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, "
				. "`title` VARCHAR(191) NOT NULL DEFAULT '', PRIMARY KEY (`id`)) ENGINE=InnoDB "
				. "DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
			SQL::query("CREATE TABLE `{$connecting}` (`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, "
				. "`mine` INT(11) NOT NULL DEFAULT 0, `theirs` INT(11) NOT NULL DEFAULT 0, PRIMARY KEY (`id`)) "
				. "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

			$module_id = BigTreeJSONDB::insert("modules", [
				"name" => "ZZ Audit17 {$suffix}",
				"route" => "zz-audit17-{$suffix}",
				// The module class list is rebuilt from every module record whenever
				// modules.json changes, and it reads this key unguarded — a fixture
				// without it leaves a warning in the next run's bootstrap.
				"class" => "",
				"forms" => [
					[
						"id" => "form-{$suffix}",
						"title" => "Entry",
						"table" => $table,
						"fields" => [
							["id" => "title", "title" => "Title", "type" => "text", "column" => "title",
								"settings" => []],
							[
								"id" => "related",
								"title" => "Related",
								"type" => "many-to-many",
								"column" => "related",
								"settings" => [
									"mtm-connecting-table" => $connecting,
									"mtm-my-id" => "mine",
									"mtm-other-id" => "theirs",
									"mtm-other-table" => $other,
								],
							],
						],
					],
					// The inverse side: a second form whose own relation points *at* the
					// table being deleted from, which is what makes the entry the target
					// of a reference rather than the owner of one.
					[
						"id" => "otherform-{$suffix}",
						"title" => "Other",
						"table" => $other,
						"fields" => [[
							"id" => "owners",
							"title" => "Owners",
							"type" => "many-to-many",
							"column" => "owners",
							"settings" => [
								"mtm-connecting-table" => $connecting,
								"mtm-my-id" => "theirs",
								"mtm-other-id" => "mine",
								"mtm-other-table" => $table,
							],
						]],
					],
				],
				"views" => [],
			]);
			T::ok($module_id !== false, "the fixture module was written to the store");

			$other_id = (int)SQL::insert($other, ["title" => "Target"]);
			$entry_id = (int)BigTreeAutoModule::createItem($table, ["title" => "Owner"], [[
				"table" => $connecting, "my-id" => "mine", "other-id" => "theirs", "data" => [(string)$other_id],
			]]);

			T::equals(
				(int)SQL::fetchSingle("SELECT COUNT(*) FROM `{$connecting}` WHERE mine = ?", $entry_id),
				1,
				"the entry owns a connecting-table row to begin with"
			);

			// A row on the *other* side of the same table, belonging to nobody being
			// deleted: it must survive, because rewriting it is another entry's edit.
			SQL::insert($connecting, ["mine" => $entry_id + 1000, "theirs" => $entry_id]);

			// A1's disclosure half, end to end: the card counts that inbound row and
			// says so, rather than describing the delete as if nothing pointed at it.
			$developer = (object)["id" => 0, "level" => 2, "permissions" => []];
			$validated = (new \BigTree\Services\AutoModuleService())->aiValidateEntryDelete([
				"module_id" => $module_id,
				"form" => "form-{$suffix}",
				"entry_id" => (string)$entry_id,
			], $developer);

			T::ok(!empty($validated["ok"]), "the delete stages for a developer");
			T::ok(
				!empty($validated["preview"]["references"]),
				"the card names what still points at the entry"
			);
			T::ok(
				strpos((string)$validated["summary"], "left pointing at it") !== false,
				"and the summary the model reads back says so too"
			);

			BigTreeAutoModule::deleteItem($table, $entry_id);

			T::equals(
				(int)SQL::fetchSingle("SELECT COUNT(*) FROM `{$connecting}` WHERE mine = ?", $entry_id),
				0,
				"deleting the entry removes the connecting rows it owned"
			);
			T::equals(
				(int)SQL::fetchSingle("SELECT COUNT(*) FROM `{$connecting}` WHERE theirs = ?", $entry_id),
				1,
				"and leaves the rows that belong to other entries for the approver to decide about"
			);
		} finally {
			// By route rather than by id: a store that refused the write still returns
			// false from insert, and a fixture module left in the store outlives the
			// run. The route is this test's own and unique either way.
			BigTreeJSONDB::delete("modules", "zz-audit17-{$suffix}", "route");

			foreach ([$table, $other, $connecting] as $drop) {
				try {
					SQL::query("DROP TABLE IF EXISTS `{$drop}`");
				} catch (\Throwable $e) {
					// The fixture is disposable; a failed drop must not fail the run.
				}
			}
		}
	}

	/**
	 * A2, behaviourally: archiving a page warns about the pages that link to it.
	 *
	 * The summary always disclosed the outbound half ("and any pages beneath it").
	 * The inbound half — every `ipl://` link that goes dead the moment the page comes
	 * off the site — was on neither card, and the CMS's own integrity scan is
	 * declined, so there was no way to find them afterwards either.
	 */
	function test_archiving_a_page_warns_about_the_pages_that_link_to_it() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$target = 0;
		$linking = 0;

		try {
			$target = parity_seed_page();
			// The shape LinkService::makeIPL stores: ipl://<navid>//<base64 commands>…
			// written through the API's bare json_encode, which escapes the slashes —
			// the harder of the two encodings a real database holds.
			$linking = parity_seed_page([
				"resources" => json_encode([
					"body" => '<p>See <a href="ipl://' . $target . '//W10=//">our pricing</a>.</p>',
				]),
			]);

			$developer = (object)["id" => 0, "level" => 2, "permissions" => []];
			$validated = (new \BigTree\Services\PageService())->aiValidatePageArchive(["id" => $target], $developer);

			T::ok(!empty($validated["ok"]), "the archive stages for a developer");
			T::ok(
				strpos((string)($validated["preview"]["warning"] ?? ""), "At least 1 other page links to this one")
					!== false,
				"the card warns that other pages link to the page being hidden"
			);

			// A page nothing links to says nothing rather than claiming zero — the same
			// rule the callout placement counter follows.
			$quiet = (new \BigTree\Services\PageService())->aiValidatePageArchive(["id" => $linking], $developer);
			T::ok(
				!isset($quiet["preview"]["warning"]),
				"and a page nothing links to carries no warning at all"
			);
		} finally {
			parity_delete_page($linking);
			parity_delete_page($target);
		}
	}

	/**
	 * JSON-store writes inside an approval seam that are deliberately not checked,
	 * and why. Every other one has to be.
	 *
	 * @return array<string,string> Class::method => reason
	 */
	function ai17_store_write_exemptions(): array {

		return [
			"BigTree\\Services\\CalloutService::aiAddCalloutToGroup" => "group membership, written to the same "
				. "store immediately after the callout record itself — an unwritable store fails that write "
				. "first and returns, so this line is unreachable with a broken store",
			"BigTree\\Services\\CalloutService::aiRemoveCalloutFromGroups" => "the same store as the callout "
				. "record written just before it, for the same reason",
		];
	}

	/**
	 * E3a: every AI approval seam that writes a developer object checks that the
	 * write landed.
	 *
	 * Parse-level, the way AIRegistryWiringTest reads auditDescriptor's body: "did
	 * the write land" is a runtime property of a call whose return value was thrown
	 * away, and nothing in the suite enumerated write primitives at all.
	 */
	function test_every_developer_object_seam_checks_that_its_write_landed() {
		$exempt = ai17_store_write_exemptions();
		$unchecked = [];
		$seams_seen = 0;

		foreach (ai17_approval_seams() as $tool => [$class, $method]) {
			$sources = [$class . "::" . $method => ai_surface_method_body($class, $method)];

			preg_match_all('/\$this->([a-zA-Z0-9_]+)\(/', $sources[$class . "::" . $method], $calls);

			foreach (array_unique($calls[1]) as $call) {
				if ($call !== $method && method_exists($class, $call)) {
					$sources[$class . "::" . $call] = ai_surface_method_body($class, $call);
				}
			}

			foreach ($sources as $reference => $source) {
				$lines = explode("\n", $source);
				$writes = 0;

				foreach ($lines as $index => $line) {
					if (!preg_match(
						'/BigTreeJSONDB::(insert|update|delete|incrementPosition|saveSubsetData)\(/',
						$line
					)) {

						continue;
					}

					$writes++;

					if (isset($exempt[$reference])) {

						continue;
					}

					// Either the call is the condition itself, or its result is bound to a
					// variable that is compared against false further down the method.
					if (preg_match('/if\s*\(\s*!?\s*BigTreeJSONDB::/', $line)) {

						continue;
					}

					if (preg_match('/\$([a-zA-Z0-9_]+)\s*=\s*BigTreeJSONDB::/', $line, $assigned)
						&& preg_match('/\$' . $assigned[1] . '\s*===\s*false/', implode("\n", array_slice($lines, $index)))) {

						continue;
					}

					$unchecked[] = "{$tool} → {$reference}: " . trim($line);
				}

				if ($writes > 0 && !isset($exempt[$reference])) {
					$seams_seen++;
				}
			}
		}

		T::ok($seams_seen >= 9, "the scan found the developer-object write seams ({$seams_seen})");
		T::equals(
			implode(" | ", $unchecked),
			"",
			"every JSON-store write in an approval seam is checked, so a store that couldn't be written is a "
				. "FAILED card rather than a green one"
		);

		// Every exemption still names a method that writes.
		$stale = [];

		foreach (array_keys($exempt) as $reference) {
			[$class, $method] = explode("::", $reference, 2);

			if (strpos(ai_surface_method_body($class, $method), "BigTreeJSONDB::") === false) {
				$stale[] = $reference;
			}
		}

		T::equals(implode(", ", $stale), "", "and every exemption names a method that still writes to the store");

		// The failure the seams return is the one that makes a card FAILED.
		T::equals(ConfigurationStore::failure()["mode"], "error", "a store write failure is reported as mode=error");
		T::ok(ConfigurationStore::failure()["message"] !== "", "and says what the approver has to fix");
	}

	/**
	 * E3b: behaviourally, save() answers the question rather than throwing.
	 *
	 * A throw out of an approval is a 500, not a FAILED card — so the primitive has
	 * to *return* false for an unwritable path, which is what lets the nine seams
	 * above turn it into a retryable proposal.
	 */
	function test_the_json_store_reports_a_write_it_could_not_make() {
		// A store name whose path lands in a directory that doesn't exist. Nothing
		// real is touched: cache() finds no file and starts from [].
		$type = "zz-audit17-missing-" . substr(md5(uniqid("", true)), 0, 8) . "/store";

		// file_put_contents warns on a missing directory; the warning is the diagnostic
		// production wants and noise here, so it is swallowed for this call only.
		set_error_handler(function (): bool {

			return true;
		});

		try {
			$result = BigTreeJSONDB::save($type);
		} catch (\Throwable $e) {
			$result = "threw: " . $e->getMessage();
		} finally {
			restore_error_handler();
			unset(BigTreeJSONDB::$Cache[$type]);
		}

		T::equals($result, false, "save() returns false for a path it cannot write, rather than throwing");

		// The encode leg: BigTree::json returns false rather than handing false to
		// file_put_contents, which writes an empty file — one invalid UTF-8 byte
		// anywhere in a store used to be enough to erase the whole store.
		T::equals(BigTree::json(["bad" => "\xB1\x31"]), false, "BigTree::json reports an encode failure as false");
		T::equals(BigTree::json(["ok" => "value"]) !== false, true, "and still encodes a valid value");

		// The write is atomic, so a crash mid-save can't leave a truncated store.
		$save = ai_surface_method_body(\BigTreeJSONDB::class, "save");
		T::ok(strpos($save, "rename(") !== false, "save() renames a complete temp file into place");
		T::ok(strpos($save, "return true;") !== false, "and reports the write it made");
	}

	/**
	 * B1/D5: the two delete paths fire the same events.
	 *
	 * REST fired `module_entry.deleted` for a live row and a discarded draft alike;
	 * the AI path fired `module_entry.draft_discarded` for the draft branch, so an
	 * extension listening on `module_entry.deleted` saw a person discard a draft and
	 * never the assistant. Both now fire both, from one place.
	 */
	function test_both_delete_paths_fire_the_same_entry_events() {
		$helper = ai_surface_method_body(\BigTree\Services\AutoModuleService::class, "fireEntryDeleted");

		T::ok(strpos($helper, '"module_entry.draft_discarded"') !== false, "the draft event is spelled once");
		T::ok(strpos($helper, '"module_entry.deleted"') !== false, "and the delete event beside it");

		foreach (["delete" => false, "aiDeleteEntry" => true] as $method => $is_ai) {
			$body = ai_surface_method_body(\BigTree\Services\AutoModuleService::class, $method);

			T::ok(
				strpos($body, '$this->fireEntryDeleted(') !== false,
				"{$method} fires its deletion events through the shared helper"
			);
			T::ok(
				strpos($body, 'Hooks::fire("module_entry.deleted"') === false
					&& strpos($body, 'Hooks::fire("module_entry.draft_discarded"') === false,
				"{$method} no longer spells the event names itself"
			);
		}
	}
