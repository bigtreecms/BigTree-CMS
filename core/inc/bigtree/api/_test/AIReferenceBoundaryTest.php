<?php
	/**
	 * Audit #11 Part B: the reference boundary.
	 *
	 * Every reference-shaped field — an image reference, a file reference, a related
	 * entry — used to be refused with the "the assistant only sets simple text-like
	 * fields" wall, on the reasoning that the assistant must never fabricate a file or
	 * a relation. But their stored values are a `bigtree_resources` id and a list of
	 * row ids: things the assistant already reads, in the same conversation, from
	 * `search_files` and `list_module_entries`. Pointing at a row that exists is a
	 * lookup, and conflating it with fabrication is what left the assistant unable to
	 * complete a record on any content model with a required photo.
	 *
	 * These cover the two halves that make the new capability safe rather than merely
	 * possible: the value is validated at staging *and* at approval (both sifts call
	 * the same resolver), and what the proposal is about is fingerprinted, so a file
	 * deleted inside the 24h TTL fails the card rather than writing a dangling id.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PageService;
	use BigTree\Services\SearchService;
	use BigTree\Services\AI\CapabilitySummary;
	use BigTree\Services\AI\FieldTypeDomain;
	use BigTree\Services\AI\ProposalFingerprint;
	use BigTree\Services\AI\RelationDomain;
	use BigTree\Services\AI\ResourceReferenceDomain;

	/** B1: the three reference types are settable, and by id. */
	function test_resource_reference_types_are_settable() {
		foreach (["image-reference", "file-reference", "video-reference"] as $type) {
			T::ok(FieldTypeDomain::isSettable($type), "`{$type}` is settable");
			T::ok(FieldTypeDomain::isResourceReference($type), "`{$type}` is recognised as a resource reference");
		}

		// And uploading still isn't: the capability that was split, not widened.
		T::ok(
			CapabilitySummary::forUser((object)["level" => 2])["can_upload_files"] === false,
			"uploading a new file is still out of scope at every level"
		);
		T::ok(
			CapabilitySummary::forUser((object)["level" => 0])["can_attach_existing_files"] === true,
			"attaching a file that already exists is in scope for any editor"
		);
	}

	/**
	 * B1: both sifts resolve a reference through the one shared resolver, and both
	 * sifts run at staging and again at approval — the "never trusted on the way back
	 * out" rule, which for an id matters more than for a value.
	 */
	function test_both_sifts_resolve_references_through_the_shared_domain() {
		foreach ([
			"entries" => [AutoModuleService::class, "aiSiftEntryData"],
			"page content" => [PageService::class, "aiSiftResourceContent"],
		] as $label => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);
			T::ok(
				strpos($body, "ResourceReferenceDomain::resolve") !== false,
				"the {$label} sift resolves references through ResourceReferenceDomain"
			);
			T::ok(
				strpos($body, "RelationDomain::resolveOneToMany") !== false,
				"the {$label} sift resolves one-to-many relations through RelationDomain"
			);
		}

		// The approval halves re-sift rather than trusting the staged id.
		foreach ([
			"create_module_entry" => [AutoModuleService::class, "aiCreateEntry"],
			"update_module_entry" => [AutoModuleService::class, "aiUpdateEntry"],
			"create_page" => [PageService::class, "aiCreatePage"],
			"update_page_content" => [PageService::class, "aiUpdatePageContent"],
		] as $tool => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);
			T::ok(
				strpos($body, "aiSiftEntryData") !== false || strpos($body, "aiSiftResourceContent") !== false,
				"{$tool} re-sifts at approval, so the reference is re-validated too"
			);
		}
	}

	/**
	 * B1: the fingerprint descriptor exists, distinguishes a deleted row from every
	 * stored state, and is refused when it names nothing — the shape check that stops
	 * a typo reading as a deliberate opt-out.
	 */
	function test_resource_fingerprint_descriptor() {
		T::equals(
			ProposalFingerprint::unsupportedReason(["type" => "resources", "ids" => [12]]),
			null,
			"a resources descriptor naming an id is supported"
		);
		T::ok(
			ProposalFingerprint::unsupportedReason(["type" => "resources", "ids" => []]) !== null,
			"a resources descriptor naming nothing is refused rather than silently unprotected"
		);

		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		// An id that cannot exist hashes as "missing", and differently from another
		// absent id — so a delete inside the TTL moves the hash.
		$absent = ProposalFingerprint::compute(["type" => "resources", "ids" => [PHP_INT_MAX]]);
		$two_absent = ProposalFingerprint::compute(["type" => "resources", "ids" => [PHP_INT_MAX, PHP_INT_MAX - 1]]);

		T::ok($absent !== "", "a resources fingerprint is a real hash, not an opt-out");
		T::ok($absent !== $two_absent, "the hash covers every id the proposal names");

		$id = (int)SQL::fetchSingle("SELECT id FROM bigtree_resources ORDER BY id ASC LIMIT 1");

		if ($id > 0) {
			T::ok(
				ProposalFingerprint::compute(["type" => "resources", "ids" => [$id]]) !== $absent,
				"an existing file hashes differently from a deleted one"
			);
		}
	}

	/** B1: an id that names nothing is a correction, not a write. */
	function test_a_reference_to_a_file_that_does_not_exist_is_refused() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$field = ["title" => "Photo", "column" => "photo", "settings" => []];
		$result = ResourceReferenceDomain::resolve(
			$field,
			"image-reference",
			(string)PHP_INT_MAX,
			(object)["level" => 2, "id" => 1]
		);

		T::ok(isset($result["error"]), "an id no file has is refused");
		T::ok(strpos((string)$result["error"], "Photo") !== false, "the error names the field");
		T::ok(
			strpos((string)$result["error"], "search_files") !== false,
			"and says where to find a real id, so the model can correct itself"
		);

		// Clearing is legal here — whether it is *allowed* is `required`'s business.
		$cleared = ResourceReferenceDomain::resolve($field, "image-reference", "", (object)["level" => 2, "id" => 1]);
		T::equals($cleared["value"] ?? null, "", "clearing a reference is left to the required gates");
	}

	/** B4: what a stored id names comes back on the read, or the write is blind. */
	function test_reference_columns_are_resolved_on_read() {
		$entry_body = ai_surface_method_body(SearchService::class, "getModuleEntryDetail");
		T::ok(
			strpos($entry_body, "ResourceReferenceDomain::describe") !== false,
			"the entry read resolves reference columns"
		);
		T::ok(
			strpos($entry_body, "entry_references") !== false,
			"and hands them to the model under entry_references"
		);

		$page_body = ai_surface_method_body(PageService::class, "aiPageContentFields");
		T::ok(
			strpos($page_body, "ResourceReferenceDomain::describe") !== false,
			"the page read resolves reference fields"
		);
		T::ok(
			strpos($page_body, "content_references") !== false,
			"and hands them to the model under content_references"
		);

		// B2's half of the same rule: a many-to-many relation lives in a connecting
		// table, so it appears nowhere in the flattened row and had to be read
		// deliberately once it became writable.
		$relation_body = ai_surface_method_body(AutoModuleService::class, "aiEntryRelationDetail");
		T::ok(
			strpos($relation_body, '"related"') !== false,
			"the entry read returns the relations the write tools can now set"
		);
	}

	/** B2: relations are settable, as lists of ids, and refuse anything else. */
	function test_relationship_fields_are_settable_by_id() {
		foreach (["one-to-many", "many-to-many"] as $type) {
			T::ok(FieldTypeDomain::isSettable($type), "`{$type}` is settable");
			T::ok(FieldTypeDomain::isRelation($type), "`{$type}` is recognised as a relation");
		}

		$field = ["title" => "Related products", "column" => "products", "settings" => ["table" => "not a table"]];
		$misconfigured = RelationDomain::resolveOneToMany($field, [1], (object)["level" => 2, "id" => 1]);
		T::ok(
			isset($misconfigured["error"]),
			"a relationship field whose target table isn't configured is refused, not guessed at"
		);

		$field = ["title" => "Related products", "column" => "products", "settings" => ["table" => "bigtree_pages"]];
		$prose = RelationDomain::resolveOneToMany($field, ["the blue one"], (object)["level" => 2, "id" => 1]);
		T::ok(isset($prose["error"]), "a relation named in prose is refused");
		T::ok(
			strpos((string)$prose["error"], "list_module_entries") !== false,
			"and says where the ids come from"
		);

		$capped = RelationDomain::resolveOneToMany(
			["title" => "Related", "settings" => ["table" => "bigtree_pages", "max" => 1]],
			[1, 2],
			(object)["level" => 2, "id" => 1]
		);
		T::ok(isset($capped["error"]), "the field's own `max` is enforced");
	}

	/**
	 * B2: the model can actually express a relation.
	 *
	 * The domain, the merge, the gates and the read were all built before anything
	 * told the model it may write one — and the write tools declared their `data` /
	 * `content` values as `{"type": "string"}`, which admits a single id and nothing
	 * else. A capability the tool contract forbids is not a capability; this is the
	 * leg that would have caught that.
	 */
	function test_the_write_tools_accept_a_list_of_ids() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$tools = [];

		foreach ($registry->availableTools($developer) as $tool) {
			$tools[$tool->name()] = $tool;
		}

		foreach ([
			"create_module_entry" => "data",
			"update_module_entry" => "data",
			"create_page" => "content",
			"update_page_content" => "content",
		] as $name => $argument) {
			T::ok(isset($tools[$name]), "{$name} is in the catalog");

			$properties = $tools[$name]->definition($developer)["function"]["parameters"]["properties"];
			$values = $properties[$argument]["additionalProperties"] ?? [];
			$shapes = [];

			foreach (is_array($values["anyOf"] ?? null) ? $values["anyOf"] : [$values] as $shape) {
				$shapes[] = (string)($shape["type"] ?? "");
			}

			T::ok(
				in_array("string", $shapes, true),
				"{$name}'s \"{$argument}\" still takes a plain value"
			);
			T::ok(
				in_array("array", $shapes, true),
				"{$name}'s \"{$argument}\" also takes a list, which is what a relation is"
			);
		}

		// And the wall that used to answer for relations names only what is still
		// genuinely out of reach.
		$out_of_scope = implode(" | ", array_keys(CapabilitySummary::outOfScope()));
		T::ok(
			strpos($out_of_scope, "relationships") === false,
			"nothing in the decline list still tells the model relationships are out of scope"
		);
		T::ok(
			CapabilitySummary::forUser((object)["level" => 0])["can_relate_entries"] === true,
			"and the capability map says so directly"
		);
	}

	/**
	 * B2: a many-to-many value rides in `$data` so the required gate, the preview and
	 * the staged payload can see it — and is taken back out before anything touches
	 * the row.
	 *
	 * A module form's fields are bound to real table columns, so the key really is a
	 * column; `many-to-many/process.php` marks the field `ignore` precisely so the
	 * admin never writes the id list into it. Left in, the assistant would be the one
	 * writer that json-encodes a relation into that column — and on a form whose
	 * relation field points at a column that has since been dropped, the staleness
	 * fingerprint would ask the database for it and throw.
	 */
	function test_a_many_to_many_value_never_reaches_the_row_or_the_fingerprint() {
		$svc = new AutoModuleService();

		T::equals(
			ai_guard_invoke($svc, "aiWithoutMtmColumns", [
				["title" => "Ada", "categories" => ["3", "7"]],
				["data" => [], "mtm" => [], "mtm_columns" => ["categories"]],
			]),
			["title" => "Ada"],
			"the relation column is dropped from the data that gets written"
		);
		T::equals(
			ai_guard_invoke($svc, "aiWithoutMtmColumns", [
				["title" => "Ada"],
				["data" => [], "mtm" => [], "mtm_columns" => []],
			]),
			["title" => "Ada"],
			"and a write with no relations in it is untouched"
		);

		// Every seam that writes a row, or asks the database about one, drops them.
		foreach ([
			"aiCreateEntry" => "the create write",
			"aiUpdateEntry" => "the update write and the queued change's blob",
			"aiValidateEntryUpdate" => "the staleness fingerprint",
		] as $method => $label) {
			T::ok(
				strpos(ai_surface_method_body(AutoModuleService::class, $method), "aiWithoutMtmColumns") !== false,
				"{$label} drops the many-to-many columns"
			);
		}

		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		// Why the fingerprint half matters: an `entry` descriptor selects the columns
		// it names, so naming one the table doesn't have is not a weak hash — it is an
		// exception thrown inside the tool call that staged the proposal.
		$threw = false;

		try {
			ProposalFingerprint::compute([
				"type" => "entry", "table" => "bigtree_pages", "id" => "1",
				"columns" => ["nav_title", "ai_guard_not_a_column"],
			]);
		} catch (\Throwable $e) {
			$threw = true;
		}

		T::ok($threw, "an entry descriptor naming a column the table lacks fails loudly, so none may name one");
	}

	/**
	 * B2: the many-to-many descriptor is built from the field's settings, never taken
	 * from the model — those identifiers are interpolated raw into SQL, which is why
	 * validateMtm exists in the first place.
	 */
	function test_many_to_many_descriptors_come_from_the_field_definition() {
		$body = ai_surface_method_body(\BigTree\Services\AI\RelationDomain::class, "resolveManyToMany");

		foreach (["mtm-connecting-table", "mtm-my-id", "mtm-other-id", "mtm-other-table"] as $setting) {
			T::ok(strpos($body, $setting) !== false, "the descriptor reads \"{$setting}\" from the field");
		}

		T::ok(
			strpos($body, "IDENTIFIER") !== false,
			"and every identifier is gated before it can reach SQL"
		);

		$loose = RelationDomain::resolveManyToMany(
			["title" => "Related", "settings" => [
				"mtm-connecting-table" => "x; DROP TABLE y", "mtm-my-id" => "a",
				"mtm-other-id" => "b", "mtm-other-table" => "c",
			]],
			[1],
			(object)["level" => 2, "id" => 1]
		);
		T::ok(isset($loose["error"]), "an identifier that isn't one is refused outright");
	}

	/**
	 * B2: an AI edit is a partial write, so authoring one relationship must not blank
	 * the entry's others — the same failure audit #4 fixed from the read side.
	 */
	function test_authoring_a_relation_does_not_blank_the_others() {
		$svc = new AutoModuleService();
		$existing = [
			["table" => "rel_a", "my-id" => "entry", "other-id" => "other", "data" => ["1", "2"]],
			["table" => "rel_b", "my-id" => "entry", "other-id" => "other", "data" => ["7"]],
		];
		$authored = [
			["table" => "rel_a", "my-id" => "entry", "other-id" => "other", "data" => ["3"]],
		];

		$merged = ai_guard_invoke($svc, "aiMergeMtm", [$existing, $authored]);

		T::equals(count($merged), 2, "the untouched relationship survives the write");

		$by_table = [];

		foreach ($merged as $entry) {
			$by_table[$entry["table"]] = $entry["data"];
		}

		T::equals($by_table["rel_a"], ["3"], "the authored relationship replaces its own triple");
		T::equals($by_table["rel_b"], ["7"], "and the other one is carried forward untouched");

		T::equals(
			ai_guard_invoke($svc, "aiMergeMtm", [$existing, []]),
			$existing,
			"a write that authors no relation carries every one of them forward"
		);
	}

	/**
	 * B2: a required relation cleared to no rows is missing. The gate compared
	 * against "" only, which a list never equals.
	 */
	function test_an_empty_required_relation_is_missing() {
		$svc = new AutoModuleService();
		$schema = ["products" => ["column" => "products", "type" => "one-to-many", "required" => true]];

		T::equals(
			ai_guard_invoke($svc, "aiMissingRequired", [$schema, ["products" => []]]),
			["products"],
			"an empty list is as missing as an empty string"
		);
		T::equals(
			ai_guard_invoke($svc, "aiMissingRequired", [$schema, ["products" => ["12"]]]),
			[],
			"and a relation with rows in it is not"
		);
	}
