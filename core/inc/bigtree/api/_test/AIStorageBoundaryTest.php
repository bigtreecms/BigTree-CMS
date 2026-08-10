<?php
	/**
	 * Audit #10 guards E2–E4: the storage boundary, the token round trip, and the
	 * prompt/decline coverage that goes with them.
	 *
	 * Audits #1–#9 each validated a proposed value against a *definition* — the form's
	 * fields, the value's own rule, the row it targets, the type its tool declared,
	 * the container it files into. Every one of them stops where the value is handed
	 * to SQL. The connection runs with `sql_mode = ''`, so from that point MySQL's
	 * answer to "this doesn't fit" is to coerce and carry on: nothing errors, the tool
	 * reports success, and the row holds something other than what the approver
	 * approved. These are the legs that make the boundary structural rather than
	 * remembered.
	 *
	 *  - E2 asserts every AI write seam that reaches a real table routes its values
	 *    through the shared ColumnDomain/TruncatedRead helpers, at staging *and* at
	 *    approval, plus that AI_PAGE_MAX_LENGTHS still matches the `max:` rules the
	 *    page routes declare (A4's drift).
	 *  - E3 asserts the link-token round trip is lossless and that every AI read seam
	 *    decodes.
	 *  - E4 asserts the truncation rule and the bulk decline line are actually in the
	 *    prompt.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PageService;
	use BigTree\Services\SettingService;
	use BigTree\Services\AI\CapabilitySummary;
	use BigTree\Services\AI\ColumnDomain;

	// — E2: the column-domain contract —

	/**
	 * Every AI write seam that hands a model-authored scalar to a real SQL table,
	 * mapped to the substrings that prove it consults the storage boundary.
	 *
	 * Shaped like AISurfaceGuardTest::ai_surface_fingerprinted(): a static map plus
	 * an enumeration leg, so a new write seam has to be mapped or explicitly exempt.
	 *
	 * @return array<string,array{0:string,1:string,2:list<string>}> label => [class, method, contains]
	 */
	function ai_storage_write_seams(): array {

		return [
			// The entry sift hands off to one place; that place does the four checks.
			"create/update_module_entry (sift)" => [
				AutoModuleService::class, "aiSiftEntryData", ["aiStorageViolation"],
			],
			"create/update_module_entry (boundary)" => [
				AutoModuleService::class, "aiStorageViolation",
				[
					"ColumnDomain::maxLengthViolation",
					"ColumnDomain::unrepresentable",
					"ColumnDomain::violation",
					"TruncatedRead::violation",
				],
			],
			// Page content lands in a longtext JSON blob, so there is no column width to
			// read — what applies is the field's own max_length, the characters the
			// connection can carry, and the truncated-read refusal.
			"create_page/update_page_content (content)" => [
				PageService::class, "aiSiftResourceContent",
				[
					"ColumnDomain::maxLengthViolation",
					"ColumnDomain::unrepresentable",
					"TruncatedRead::violation",
				],
			],
			// The page's own scalar columns: the cap list plus the 4-byte check.
			"create_page/update_page (fields)" => [
				PageService::class, "aiPageLengthError",
				["AI_PAGE_MAX_LENGTHS", "ColumnDomain::unrepresentable"],
			],
			// The positive control this whole boundary was generalized from (audit #3).
			// The email leg is `aiSettingRuleViolation` now, not a branch keyed on an
			// `email` field type that this CMS has never had (audit #11 A3).
			"update_setting" => [
				SettingService::class, "aiCheckSettingValue",
				["strtotime", "aiSettingRuleViolation", "FieldTypeDomain::linkShapeViolation"],
			],
		];
	}

	/**
	 * The approval half. A staged value sits in the proposal store for up to 24h and
	 * is never trusted on the way back out — and a table can be altered inside that
	 * window, so the storage boundary has to be re-asked, not just re-read.
	 *
	 * @return array<string,array{0:string,1:string,2:string}> tool => [class, approval method, sift]
	 */
	function ai_storage_approval_seams(): array {

		return [
			"create_module_entry" => [AutoModuleService::class, "aiCreateEntry", "aiSiftEntryData"],
			"update_module_entry" => [AutoModuleService::class, "aiUpdateEntry", "aiSiftEntryData"],
			"create_page" => [PageService::class, "aiCreatePage", "aiSiftResourceContent"],
			"update_page_content" => [PageService::class, "aiUpdatePageContent", "aiSiftResourceContent"],
		];
	}

	/** E2: every write seam consults the storage boundary. */
	function test_every_write_seam_checks_the_storage_boundary() {
		$missing = [];

		foreach (ai_storage_write_seams() as $label => [$class, $method, $contains]) {
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$label}: {$method}'s source was read");

			foreach ($contains as $needle) {
				if (strpos($body, $needle) === false) {
					$missing[] = "{$label} is missing “{$needle}”";
				}
			}
		}

		T::equals(implode(", ", $missing), "", "every AI write seam consults the storage boundary");
	}

	/** E2: and re-consults it at approval, not only at staging. */
	function test_every_write_seam_re_sifts_at_approval() {
		$missing = [];

		foreach (ai_storage_approval_seams() as $tool => [$class, $method, $sift]) {
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$tool}: {$method}'s source was read");

			if (strpos($body, $sift) === false) {
				$missing[] = "{$tool} ({$method}) never calls {$sift}";
			}
		}

		T::equals(implode(", ", $missing), "", "every approval seam re-sifts against the storage boundary");
	}

	/**
	 * E2 enumeration: every mutating tool that writes model-authored text into a real
	 * SQL table is covered by a seam above, or carries an explicit reason.
	 *
	 * @return array<string,string>
	 */
	function ai_storage_tool_coverage(): array {

		return [
			"create_module_entry" => "create/update_module_entry (sift)",
			"update_module_entry" => "create/update_module_entry (sift)",
			"create_page" => "create_page/update_page (fields)",
			"update_page" => "create_page/update_page (fields)",
			"update_page_content" => "create_page/update_page_content (content)",
			"update_setting" => "update_setting",
		];
	}

	/**
	 * Mutating tools that write no model-authored free text into a SQL column, with
	 * the reason recorded rather than skipped.
	 *
	 * @return array<string,string>
	 */
	function ai_storage_tool_exempt(): array {

		return [
			"archive_page" => "flags only",
			"unarchive_page" => "flags only",
			"move_page" => "moves a row; writes no authored value",
			"delete_module_entry" => "deletes a row",
			"set_module_entry_flag" => "flags only",
			"add_tags" => "tag names are capped inline at both passes (audit #5 Phase 4)",
			"remove_tags" => "detaches existing tags; authors nothing",
			"merge_tags" => "operates on existing tag records",
			"rename_tag" => "tag names are capped inline at both passes",
			"publish_pending_change" => "replays a change written by some other seam",
			"reject_pending_change" => "deletes a queued change",
			"save_page_revision" => "snapshots the page as stored",
			"restore_page_revision" => "replays a stored snapshot",
			"create_user" => "aiUserLengthError caps and 4-byte-checks every authored field at both passes",
			"update_user" => "aiUserLengthError caps and 4-byte-checks every authored field at both passes",
			"create_redirect" => "writes into bigtree_404s' varchar(1024) columns; shape-gated to URLs by aiCheckRedirectDestination",
			"create_template" => "JSON-DB record, not a SQL column",
			"update_template" => "JSON-DB record, not a SQL column",
			"create_callout" => "JSON-DB record, not a SQL column",
			"update_callout" => "JSON-DB record, not a SQL column",
			"create_callout_group" => "JSON-DB record; name capped by AI_GROUP_NAME_MAX_LENGTH",
			"create_module" => "JSON-DB record, not a SQL column",
			// Writes DDL rather than values: it *creates* the columns, and the table it
			// creates is required not to exist, so there is no column domain to fit a
			// value into. What guards it instead is scaffoldPlan's identifier and
			// collision checks, re-run at approval.
			"scaffold_module" => "creates columns rather than writing into them",
			"update_module" => "JSON-DB record, not a SQL column",
			"create_module_group" => "JSON-DB record; name capped by AI_GROUP_NAME_MAX_LENGTH",
		];
	}

	/** E2: a new mutating tool must be covered or explicitly exempt. */
	function test_every_mutating_tool_is_storage_classified() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$covered = ai_storage_tool_coverage();
		$exempt = ai_storage_tool_exempt();
		$seams = ai_storage_write_seams();
		$unclassified = [];
		$bad_reference = [];

		foreach ($registry->availableTools($developer) as $tool) {
			if ($tool->kind() === "read") {

				continue;
			}

			$name = $tool->name();

			if (isset($covered[$name])) {
				if (!isset($seams[$covered[$name]])) {
					$bad_reference[] = "{$name} → {$covered[$name]}";
				}

				continue;
			}

			if (!isset($exempt[$name])) {
				$unclassified[] = $name;
			}
		}

		T::equals(implode(", ", $unclassified), "", "every mutating tool is storage-covered or explicitly exempt");
		T::equals(implode(", ", $bad_reference), "", "and every coverage entry names a real write seam");
	}

	/**
	 * The `max:` rules a route file declares, as field => cap.
	 *
	 * "nav_title" => "required|string|max:1024" — the only place these caps are
	 * stated for REST, thousands of lines from the consts that mirror them by hand.
	 *
	 * @return array<string,int>
	 */
	function ai_route_max_rules(string $file): array {
		$path = __DIR__ . "/../routes/" . $file;
		T::ok(is_readable($path), "routes/{$file} is readable");

		$source = (string)file_get_contents($path);
		$declared = [];

		if (preg_match_all('/"([a-z_]+)"\s*=>\s*"([^"]*max:(\d+)[^"]*)"/', $source, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$declared[$match[1]] = (int)$match[3];
			}
		}

		T::ok(count($declared) > 5, "routes/{$file} declares max: rules we could parse");

		return $declared;
	}

	/**
	 * E2/A4: AI_PAGE_MAX_LENGTHS must not drift from what routes/pages.php declares,
	 * in *either* direction.
	 *
	 * The forward leg (the const disagreeing with a rule) is the obvious one. The
	 * reverse leg is the likelier drift and the one that actually costs data: a page
	 * route gaining a `max:` for a field the assistant writes, with nothing adding it
	 * to the const, leaves that field capped for REST and uncapped for the assistant
	 * — and in non-strict mode MySQL then truncates it in silence.
	 */
	function test_page_max_lengths_match_the_route_rules() {
		$declared = ai_route_max_rules("pages.php");
		$reflection = new ReflectionClass(PageService::class);
		$mirrored = $reflection->getConstant("AI_PAGE_MAX_LENGTHS");
		T::ok(is_array($mirrored), "AI_PAGE_MAX_LENGTHS is readable");

		$drift = [];

		foreach ($mirrored as $field => $max) {
			if (!isset($declared[$field])) {
				$drift[] = "{$field} is capped by the assistant but by no page route rule";

				continue;
			}

			if ($declared[$field] !== (int)$max) {
				$drift[] = "{$field}: assistant {$max}, route {$declared[$field]}";
			}
		}

		T::equals(implode("; ", $drift), "", "AI_PAGE_MAX_LENGTHS matches routes/pages.php");

		// Scoped to AI_PAGE_FIELDS: a cap on a field the assistant can't write is the
		// route's business alone.
		$uncapped = [];

		foreach (PageService::AI_PAGE_FIELDS as $field) {
			if (isset($declared[$field]) && !isset($mirrored[$field])) {
				$uncapped[] = "{$field} (route says max:{$declared[$field]})";
			}
		}

		T::equals(
			implode(", ", $uncapped),
			"",
			"every page field the assistant writes that a route caps is capped by AI_PAGE_MAX_LENGTHS"
		);
	}

	/**
	 * E2/A4: AI_USER_MAX_LENGTHS has exactly the same shape against routes/users.php,
	 * and had no guard at all.
	 *
	 * The reverse leg is derived from create_user's own argument schema rather than a
	 * hand-listed set, so a new authored string field has to be capped or the leg
	 * fails — the route file also caps `password` and `per_page`, which the assistant
	 * never writes.
	 */
	function test_user_max_lengths_match_the_route_rules() {
		$declared = ai_route_max_rules("users.php");
		$reflection = new ReflectionClass(\BigTree\Services\UserService::class);
		$mirrored = $reflection->getConstant("AI_USER_MAX_LENGTHS");
		T::ok(is_array($mirrored), "AI_USER_MAX_LENGTHS is readable");

		$drift = [];

		foreach ($mirrored as $field => $max) {
			if (!isset($declared[$field])) {
				$drift[] = "{$field} is capped by the assistant but by no user route rule";

				continue;
			}

			if ($declared[$field] !== (int)$max) {
				$drift[] = "{$field}: assistant {$max}, route {$declared[$field]}";
			}
		}

		T::equals(implode("; ", $drift), "", "AI_USER_MAX_LENGTHS matches routes/users.php");

		$authored = [];

		foreach (ai_wiring_registry()->availableTools(ai_wiring_user(2)) as $tool) {
			if ($tool->name() !== "create_user") {

				continue;
			}

			$properties = $tool->definition(ai_wiring_user(2))["function"]["parameters"]["properties"] ?? [];

			foreach ((array)$properties as $field => $spec) {
				if ((string)($spec["type"] ?? "") === "string") {
					$authored[] = (string)$field;
				}
			}
		}

		T::ok($authored !== [], "create_user declares string arguments");

		$uncapped = [];

		foreach ($authored as $field) {
			if (isset($declared[$field]) && !isset($mirrored[$field])) {
				$uncapped[] = "{$field} (route says max:{$declared[$field]})";
			}
		}

		T::equals(
			implode(", ", $uncapped),
			"",
			"every authored user string field a route caps is capped by AI_USER_MAX_LENGTHS"
		);
	}

	/** E2: the column domain judges each type the way MySQL would silently coerce it. */
	function test_column_domain_refuses_what_mysql_would_coerce() {
		$field = ["column" => "headline", "type" => "text", "title" => "Headline"];

		// Length: the audit's opening case — a 400-word summary into a varchar(255).
		$value = str_repeat("a", 300);
		T::ok(
			ColumnDomain::violationForColumn(["type" => "varchar", "size" => "255"], $field, $value) !== null,
			"an over-long value for a varchar(255) is refused"
		);

		$value = str_repeat("a", 200);
		T::equals(
			ColumnDomain::violationForColumn(["type" => "varchar", "size" => "255"], $field, $value),
			null,
			"and a value that fits is not"
		);

		// Dates: "next Tuesday" silently becomes 0000-00-00.
		$date = ["column" => "starts", "type" => "date", "title" => "Starts"];
		$value = "next Tuesday";
		T::equals(
			ColumnDomain::violationForColumn(["type" => "date"], $date, $value),
			null,
			"a resolvable date is accepted"
		);
		T::ok(
			(bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value),
			"and normalized to the column's format (got \"{$value}\")"
		);

		$value = "sometime soonish";
		T::ok(
			ColumnDomain::violationForColumn(["type" => "date"], $date, $value) !== null,
			"an unparseable date is refused rather than stored as 0000-00-00"
		);

		// Numbers: a non-numeric string becomes 0.
		$number = ["column" => "seats", "type" => "number", "title" => "Seats"];
		$value = "about a hundred";
		T::ok(
			ColumnDomain::violationForColumn(["type" => "int", "size" => "11"], $number, $value) !== null,
			"a non-numeric value for an integer column is refused"
		);

		$value = "120";
		T::equals(
			ColumnDomain::violationForColumn(["type" => "int", "size" => "11"], $number, $value),
			null,
			"and a real number is not"
		);

		// Enums: an out-of-domain value becomes ''.
		$enum_column = ["type" => "enum", "options" => ["draft", "live"]];
		$status = ["column" => "status", "type" => "select", "title" => "Status"];
		$value = "published";
		T::ok(
			ColumnDomain::violationForColumn($enum_column, $status, $value) !== null,
			"a value outside an enum's domain is refused"
		);

		$value = "Live";
		T::equals(
			ColumnDomain::violationForColumn($enum_column, $status, $value),
			null,
			"a recognisable label is matched back to its value"
		);
		T::equals($value, "live", "and rewritten in place");

		// A checkbox's "on" is never judged as a number, however the column is typed.
		$checkbox = ["column" => "featured", "type" => "checkbox", "title" => "Featured"];
		$value = "on";
		T::equals(
			ColumnDomain::violationForColumn(["type" => "int", "size" => "1"], $checkbox, $value),
			null,
			"a checkbox's \"on\" is not judged against an integer column"
		);
	}

	/**
	 * E2/A2(2): every seam that writes model-authored text into a utf8mb3 column
	 * checks for characters the connection can't carry.
	 *
	 * A separate axis from length, and the exempt list above is about length — so a
	 * seam can be perfectly capped and still hand MySQL a string it amputates at the
	 * first emoji. The tables here are the ones that stay utf8mb3 until revision 512
	 * converts them (base.sql itself declares utf8mb4 now, so this is about installs
	 * that upgraded); module tables inherit the install's default and are covered by
	 * the entry sift regardless.
	 *
	 * @return array<string,array{0:string,1:string,2:string}> table => [class, method, why]
	 */
	function ai_utf8mb3_write_seams(): array {

		return [
			// base.sql:39
			"bigtree_pages" => [PageService::class, "aiPageLengthError", ""],
			// base.sql:69
			"bigtree_users" => [\BigTree\Services\UserService::class, "aiUserLengthError", ""],
			// Module tables: whatever the install defaults to, so always checked.
			"module entry tables" => [AutoModuleService::class, "aiStorageViolation", ""],
			// base.sql:63 — TagService::normalize strips everything outside
			// [a-zA-Z0-9 ] before storage, so no 4-byte character can reach the column.
			"bigtree_tags" => [
				\BigTree\Services\TagService::class, "normalize",
				"exempt: normalize() strips to [a-zA-Z0-9 ]",
			],
			// base.sql:9 — a destination must be an absolute URL, a root-relative path
			// or an ipl://{wwwroot} token before it is ever stored.
			"bigtree_404s" => [
				\BigTree\Services\FourOhFourService::class, "aiCheckRedirectDestination",
				"exempt: shape-gated to URLs",
			],
		];
	}

	/** E2/A2(2): a utf8mb3 write seam either checks, or records why it needn't. */
	function test_every_utf8mb3_write_seam_checks_representability() {
		$missing = [];

		foreach (ai_utf8mb3_write_seams() as $table => [$class, $method, $why]) {
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$table}: {$method}'s source was read");

			if ($why !== "") {
				T::ok($why !== "", "{$table} is exempt — {$why}");

				continue;
			}

			if (strpos($body, "ColumnDomain::unrepresentable") === false) {
				$missing[] = "{$table} ({$method})";
			}
		}

		T::equals(implode(", ", $missing), "", "every utf8mb3 write seam checks for unrepresentable characters");
	}

	/** E2/A2(2): the tag exemption is only true while normalize really does strip. */
	function test_tag_names_cannot_carry_a_four_byte_character() {
		$body = ai_surface_method_body(\BigTree\Services\TagService::class, "normalize");

		T::ok(
			strpos($body, 'a-zA-Z0-9') !== false,
			"TagService::normalize still strips to an ASCII-only character class"
		);
	}

	/** E2/A2(2): 4-byte characters are named rather than silently amputated. */
	function test_over_plane_characters_are_refused() {
		T::ok(
			ColumnDomain::unrepresentable("Headline", "Spring Gala 🎉 tickets") !== null,
			"an emoji is refused while a target column may still be utf8mb3"
		);
		T::equals(
			ColumnDomain::unrepresentable("Headline", "Spring Gala — “tickets”"),
			null,
			"em-dashes and curly quotes are 3-byte and pass"
		);
		T::equals(
			ColumnDomain::unrepresentable("Headline", ""),
			null,
			"an empty value has nothing to check"
		);
	}

	/** E2/A3: the max_length field setting is enforced somewhere other than the browser. */
	function test_maxlength_is_enforced_server_side() {
		T::ok(
			ColumnDomain::maxLengthViolation("Meta title", 60, str_repeat("a", 61)) !== null,
			"a value past the field's max_length is refused"
		);
		T::equals(
			ColumnDomain::maxLengthViolation("Meta title", 60, str_repeat("a", 60)),
			null,
			"and a value exactly at it is not"
		);
		T::equals(
			ColumnDomain::maxLengthViolation("Meta title", 0, str_repeat("a", 5000)),
			null,
			"a field with no max_length is uncapped by this check"
		);

		// Audit #11 A1: the cap above is only reachable if the seams read the key the
		// CMS actually stores. Build the settings blob the way the field type declares
		// it (E4) rather than by hand, so this can't agree with a misspelling again.
		$declared = ai_field_setting_ids("text");
		T::ok(in_array("max_length", $declared, true), "`text` declares a max_length setting");
		T::equals(
			ColumnDomain::configuredMaxLength(["max_length" => 60]),
			60,
			"the stored spelling is read"
		);
		T::equals(
			ColumnDomain::configuredMaxLength(["maxlength" => 60]),
			60,
			"a legacy blob's misspelling still caps"
		);
		T::equals(ColumnDomain::configuredMaxLength([]), 0, "an unset cap is 0");

		// Both schemas have to feed it or the sift has nothing to check against.
		foreach ([
			"entries" => [AutoModuleService::class, "aiEntrySchema"],
			"page content" => [PageService::class, "aiTemplateResourceSchema"],
		] as $label => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);
			T::ok(
				strpos($body, "ColumnDomain::configuredMaxLength") !== false,
				"the {$label} schema reads the cap through configuredMaxLength"
			);
			T::ok(strpos($body, '"max_length"') !== false, "the {$label} schema emits max_length");
		}
	}

	// — E1/A1: field settings are read by their declared key —

	/**
	 * Keys the AI seams read out of a `settings` blob that are deliberately *not*
	 * field-type settings descriptors, each with the reason it isn't one.
	 *
	 * Anything not on this list has to be a `settings_schema` descriptor id in
	 * core/inc/bigtree/api/field-type-schemas.php. That file's own contract comment
	 * says the descriptor id "MUST match what the field type's draw.php / process.php
	 * reads", which makes it the only authority on the spelling of a stored setting —
	 * and `maxlength`, which no field type has ever written, sat in three AI seams for
	 * two audits because nothing compared the two (audit #11 A1/E1).
	 *
	 * @return array<string,string> key => why it is not a field setting
	 */
	function ai_non_descriptor_setting_keys(): array {

		return [
			// Form-editor keys that live beside the type's own settings rather than in
			// its schema: every field type carries them, no field type declares them.
			"validation" => "the form editor's whitespace rule string, on every field type",
			"required" => "the form editor's own required flag, on every field type",
			"error_message" => "the form editor's per-field validation message",
			// View settings, not field settings — same blob name, different owner.
			"per_page" => "a module view setting",
			"filter" => "a module view setting",
			// Written by the SPA's own field renderers, not by a settings.php.
			"placeholder" => "a renderer hint, not a stored field-type setting",
			"rows" => "a legacy textarea renderer hint",
		];
	}

	/**
	 * Exceptions that hold in exactly one file, because that file is the single place
	 * allowed to know about the spelling.
	 *
	 * `maxlength` is the whole point of this leg: it is not a key the CMS stores, so
	 * a blanket exemption for it would make E1 blind to precisely the defect it exists
	 * to catch. ColumnDomain::configuredMaxLength() is allowed to read it as a legacy
	 * fallback; no seam is allowed to read it directly.
	 *
	 * @return array<string,array<string,string>> file label => [key => reason]
	 */
	function ai_scoped_setting_key_exceptions(): array {

		return [
			"AI/ColumnDomain" => [
				"maxlength" => "the legacy misspelling, read as a fallback by configuredMaxLength() only",
			],
		];
	}

	/**
	 * The declared `settings_schema` descriptor ids for one field type.
	 *
	 * @return list<string>
	 */
	function ai_field_setting_ids(string $type): array {
		$schemas = ai_field_type_schemas();
		$descriptors = is_array($schemas[$type]["settings_schema"] ?? null)
			? $schemas[$type]["settings_schema"]
			: [];

		return array_values(array_filter(array_map(function ($descriptor): string {

			return is_array($descriptor) ? (string)($descriptor["id"] ?? "") : "";
		}, $descriptors)));
	}

	/**
	 * Every field-type schema, as declared.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function ai_field_type_schemas(): array {
		static $schemas = null;

		if ($schemas === null) {
			$schemas = (array)(require __DIR__ . "/../field-type-schemas.php");
		}

		return $schemas;
	}

	/**
	 * Every descriptor id declared by any field type — including the reserved
	 * `_universal` entry, whose settings_schema every field type carries (see
	 * FieldTypeService::universalSettingsSchema).
	 *
	 * A universal descriptor is a *declaration*, not an exemption, which is the point
	 * of audit #19's E2. `default` had two readers (PageService::normalizePageResources
	 * and the SPA's FormRenderer) and no writer anywhere in the product, so E1 was
	 * red — and the fix a blanket entry in ai_non_descriptor_setting_keys() would
	 * have been is precisely what audit #11 refused for `maxlength`: it blinds the
	 * check to the defect it exists for. Declaring the setting instead means the
	 * field-settings UI renders it and the field-authoring tools can author it.
	 */
	function ai_all_declared_setting_ids(): array {
		$ids = [];

		foreach (array_keys(ai_field_type_schemas()) as $type) {
			$ids = array_merge($ids, ai_field_setting_ids((string)$type));
		}

		return array_values(array_unique($ids));
	}

	/**
	 * E2 (#19): `default` is a declared setting rather than an exempted key.
	 */
	function test_the_universal_default_setting_is_declared_not_exempted() {
		$universal = \BigTree\Services\FieldTypeService::universalSettingsSchema();
		$ids = array_map(function ($descriptor): string {

			return (string)($descriptor["id"] ?? "");
		}, $universal);

		T::ok(in_array("default", $ids, true), "`default` is declared as a universal field setting");
		T::ok(
			in_array("default", ai_all_declared_setting_ids(), true),
			"…so E1 sees it as a declared descriptor id rather than an unknown key"
		);
		T::ok(
			!isset(ai_non_descriptor_setting_keys()["default"]),
			"…and it is not blanket-exempted, which would blind E1 to the next such key"
		);
	}

	/**
	 * The descriptor ids a field type's `settings_schema` declares, universal ones
	 * appended — i.e. what the SPA's settings editor renders and what the AI
	 * authoring seams treat as a setting this type has.
	 *
	 * @return list<string>
	 */
	function ai_effective_setting_ids(string $type): array {

		return array_map(function ($descriptor): string {

			return (string)($descriptor["id"] ?? "");
		}, \BigTree\Services\FieldTypeService::settingsSchema($type));
	}

	/**
	 * Audit #20 guard E2: a universal descriptor is universal only where the value
	 * type allows it.
	 *
	 * Keyed off each schema's own `value_type` rather than a list of type ids here,
	 * so a field type added later — or shipped by an extension through
	 * custom/inc/bigtree/api/field-type-schemas.php — is classified by declaring what
	 * shape its value is and nothing has to be added to this test.
	 *
	 * The negative control is the leg that matters: `default` on a `matrix` or a
	 * `media-gallery` is a scalar handed to a template's `foreach`, and on an
	 * `image-reference` a resource id nothing resolved (audit #20 A2).
	 */
	function test_the_universal_default_is_declared_only_where_a_scalar_means_something() {
		$scalar = ["string", "bool"];
		$wrong = [];
		$checked = 0;

		foreach (["templates", "modules", "callouts", "settings"] as $use_case) {
			foreach (\BigTree\Services\FieldTypeService::availableFieldTypeIds($use_case) as $type) {
				$value_type = \BigTree\Services\FieldTypeService::valueType((string)$type);

				// A type this file doesn't declare (a custom or extension type) keeps
				// every universal setting — scoping must never silently remove one from
				// a type nobody classified.
				if ($value_type === "") {

					continue;
				}

				$checked++;
				$declared = in_array("default", ai_effective_setting_ids((string)$type), true);
				$expected = in_array($value_type, $scalar, true);

				if ($declared !== $expected) {
					$wrong[] = "{$type} ({$value_type}) " . ($declared ? "carries" : "lacks") . " `default`";
				}
			}
		}

		T::ok($checked > 0, "the field-type catalog resolved, so this checked something");
		T::equals(
			implode(", ", array_unique($wrong)),
			"",
			"`default` is declared iff the field type's value_type is a scalar"
		);

		// The explicit controls, so a catalog that silently stopped resolving can't
		// pass this by finding nothing.
		foreach (["text", "html", "image", "list", "checkbox"] as $type) {
			T::ok(
				in_array("default", ai_effective_setting_ids($type), true),
				"the {$type} field type carries the universal `default`"
			);
		}

		foreach (["matrix", "media-gallery", "callouts", "image-reference", "video"] as $type) {
			T::ok(
				!in_array("default", ai_effective_setting_ids($type), true),
				"the {$type} field type, whose value isn't a scalar, does not"
			);
		}

		// …and the descriptor itself still lands on a type that declares no settings
		// of its own, which is what "universal" continues to mean.
		T::ok(
			in_array("default", ai_effective_setting_ids("link"), true),
			"a field type declaring no settings of its own still gets the universal one"
		);
	}

	/**
	 * D4: what normalizePageResources fills for a field with no default is the empty
	 * value of that field's *type*.
	 *
	 * The function exists so a front-end template never hits an undefined variable.
	 * Handing a `foreach` over a matrix an empty string rather than an empty array
	 * trades one warning for a worse one — and a scalar `default` stored on an
	 * array-valued field before audit #20 A2 stopped those types declaring one is
	 * ignored rather than written through.
	 */
	function test_page_resources_are_filled_with_the_empty_value_of_their_type() {
		$normalize = new ReflectionMethod(\BigTree\Services\PageService::class, "normalizePageResources");
		$normalize->setAccessible(true);
		$service = new \BigTree\Services\PageService();
		$template = "zz_default_shape_" . bin2hex(random_bytes(4));

		BigTreeJSONDB::insert("templates", [
			"id" => $template,
			"name" => "ZZ Default Shape",
			"routed" => "",
			"level" => 0,
			"module" => "",
			"resources" => [
				["id" => "headline", "type" => "text", "title" => "Headline", "settings" => ["default" => "Hello"]],
				["id" => "blurb", "type" => "text", "title" => "Blurb", "settings" => []],
				["id" => "rows", "type" => "matrix", "title" => "Rows", "settings" => ["default" => "oops"]],
				["id" => "blocks", "type" => "callouts", "title" => "Blocks", "settings" => []],
			],
		]);

		try {
			$filled = $normalize->invoke($service, $template, []);

			T::equals($filled["headline"] ?? null, "Hello", "a scalar field takes its configured default");
			T::equals($filled["blurb"] ?? null, "", "…and \"\" when it has none");
			T::equals($filled["blocks"] ?? null, [], "an array-valued field is filled with an empty array");
			T::equals(
				$filled["rows"] ?? null,
				[],
				"…and a scalar default left on one by an older blob is ignored, not handed to a foreach"
			);

			// Never over a value the page already carries.
			$kept = $normalize->invoke($service, $template, ["headline" => "Set by an editor"]);
			T::equals($kept["headline"] ?? null, "Set by an editor", "an existing value is preserved");
		} finally {
			BigTreeJSONDB::delete("templates", $template);
		}
	}

	/**
	 * Every file E1 scans: each one reaches into a stored `settings` blob by
	 * hand-written key, which is the only way the A1 defect can be reintroduced.
	 *
	 * Declared once, and asserted total by
	 * test_e1_scans_every_seam_that_reads_field_settings — a new AI domain class
	 * that reads a setting and isn't listed here is a seam this guard can't see.
	 *
	 * @return array<string,string> label => absolute path
	 */
	function ai_setting_key_scanned_files(): array {

		return [
			"PageService" => __DIR__ . "/../../services/PageService.php",
			"AutoModuleService" => __DIR__ . "/../../services/AutoModuleService.php",
			"SettingService" => __DIR__ . "/../../services/SettingService.php",
			"api/Resources" => __DIR__ . "/../Resources.php",
			"AI/ColumnDomain" => __DIR__ . "/../../services/AI/ColumnDomain.php",
			"AI/FieldOptionDomain" => __DIR__ . "/../../services/AI/FieldOptionDomain.php",
			// Audit #11's own new seams: the reference resolver reads min_width /
			// min_height, and the relation resolver reads the mtm-* triple, `table`
			// and `max`. They read settings by hand-written key exactly as the older
			// seams do, so they are exactly what E1 is for.
			"AI/ResourceReferenceDomain" => __DIR__ . "/../../services/AI/ResourceReferenceDomain.php",
			"AI/RelationDomain" => __DIR__ . "/../../services/AI/RelationDomain.php",
		];
	}

	/**
	 * E1: every settings key the AI seams read is a key the CMS actually stores.
	 *
	 * Fails on `$settings["maxlength"]` as the code stood at 612ef9e10 — the defect
	 * A1 describes, which a test written from the implementation could not see.
	 */
	function test_ai_seams_read_declared_field_setting_keys() {
		$declared = ai_all_declared_setting_ids();
		$exempt = ai_non_descriptor_setting_keys();
		$scoped = ai_scoped_setting_key_exceptions();
		$files = ai_setting_key_scanned_files();
		$unknown = [];

		foreach ($files as $label => $path) {
			$source = (string)file_get_contents($path);
			$matches = [];
			// $settings["key"] and $anything["settings"]["key"] — the two spellings
			// every one of these seams uses to reach a stored field setting.
			preg_match_all('/\$settings\[\s*"([a-z0-9_\-]+)"/i', $source, $matches);
			$keys = $matches[1];
			preg_match_all('/\["settings"\]\[\s*"([a-z0-9_\-]+)"/i', $source, $matches);
			$keys = array_merge($keys, $matches[1]);

			foreach (array_unique($keys) as $key) {
				if (in_array($key, $declared, true) || isset($exempt[$key]) || isset($scoped[$label][$key])) {

					continue;
				}

				$unknown[] = "{$label}: \"{$key}\"";
			}
		}

		T::equals(
			implode(", ", $unknown),
			"",
			"every field setting the AI seams read is a declared descriptor id or an explained exception"
		);
	}

	/**
	 * E1 (the other direction): the exception list stays honest — an entry that names
	 * a key the schemas *do* declare is stale and hides the check it was written for.
	 */
	function test_the_setting_key_exception_list_has_no_stale_entries() {
		$declared = ai_all_declared_setting_ids();
		$stale = [];

		foreach (ai_non_descriptor_setting_keys() as $key => $reason) {
			if (in_array($key, $declared, true)) {
				$stale[] = $key;
			}

			T::ok($reason !== "", "the \"{$key}\" exception states why it isn't a field setting");
		}

		T::equals(implode(", ", $stale), "", "no exempted key is actually a declared descriptor id");

		// The scoped exceptions name a file this test actually scans, or they exempt
		// nothing and quietly stop protecting the seam they were written for.
		foreach (ai_scoped_setting_key_exceptions() as $label => $keys) {
			T::ok(
				isset(ai_setting_key_scanned_files()[$label]),
				"the \"{$label}\" scoped exception names a file E1 scans"
			);

			foreach ($keys as $key => $reason) {
				T::ok($reason !== "", "the {$label}/\"{$key}\" scoped exception states its reason");
			}
		}
	}

	/**
	 * E1 (the coverage direction): every AI domain class that reads a field setting
	 * is a file E1 scans.
	 *
	 * The guard is a scan over a hand-written file list, so it protects exactly what
	 * that list names — and audit #11 added two new classes that read settings by
	 * hand-written key without adding them to it. A guard whose scope has to be
	 * remembered is a guard that lapses; this leg discovers the seams instead.
	 */
	function test_e1_scans_every_seam_that_reads_field_settings() {
		$scanned = array_map("realpath", ai_setting_key_scanned_files());
		$unscanned = [];

		foreach ((array)glob(__DIR__ . "/../../services/AI/*.php") as $path) {
			$source = (string)file_get_contents($path);

			// The same two spellings the scan itself looks for.
			if (!preg_match('/\$settings\[\s*"/i', $source) && !preg_match('/\["settings"\]\[\s*"/i', $source)) {

				continue;
			}

			if (!in_array(realpath($path), $scanned, true)) {
				$unscanned[] = basename($path);
			}
		}

		T::equals(
			implode(", ", $unscanned),
			"",
			"every AI domain class that reads a field setting is scanned by E1"
		);
	}

	// — E3: the link-token round trip —

	/**
	 * E3: decoding a read and re-tokenizing the write is lossless in both directions.
	 *
	 * This is the property that makes B3's fix a small change rather than a design
	 * question: the model can be shown a hard link, edit the prose around it, and have
	 * the sift put the stored token back exactly as it was.
	 */
	function test_link_token_round_trip_is_lossless() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$path = (string)SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE path != '' ORDER BY id ASC LIMIT 1");

		if ($path === "") {

			return;
		}

		// Start from the URL a human would paste, which is what the model would write.
		$authored = '<p>Read our <a href="' . WWW_ROOT . $path . '/">pricing page</a> for details.</p>';
		$stored = PageService::aiNormalizeHtmlValue($authored);

		T::ok(strpos($stored, "ipl://") !== false, "the write tokenizes an internal link");

		$decoded = PageService::aiDenormalizeHtmlValue($stored);

		T::ok(strpos($decoded, "ipl://") === false, "the read decodes it back to a hard link");
		T::equals($decoded, $authored, "and the decode returns exactly what was authored");
		T::equals(
			PageService::aiNormalizeHtmlValue($decoded),
			$stored,
			"and re-tokenizing the decoded value reproduces the stored form byte for byte"
		);
	}

	/**
	 * E3: {wwwroot} survives the same round trip.
	 *
	 * Byte identity isn't the contract here — replaceHardRoots picks whichever root
	 * token matches first, so `{wwwroot}` can come back as `{staticroot}` on an
	 * install where the two are the same URL (this is autoIPL's long-standing
	 * behaviour, shared with the admin's own form pipeline). What must hold is that
	 * the link the model read is the link that ends up stored.
	 */
	function test_relative_root_round_trip_is_lossless() {
		$stored = '<p>See <a href="{wwwroot}about/">about us</a>.</p>';
		$decoded = PageService::aiDenormalizeHtmlValue($stored);

		T::ok(strpos($decoded, "{wwwroot}") === false, "the read expands {wwwroot}");

		$retokenized = PageService::aiNormalizeHtmlValue($decoded);

		T::ok(strpos($retokenized, "http") === false, "the write contracts the hard root back to a token");
		T::equals(
			PageService::aiDenormalizeHtmlValue($retokenized),
			$decoded,
			"and the stored token resolves to the same link the model was shown"
		);
	}

	/**
	 * E3: every AI read seam that can return a markup-bearing value decodes it.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	function ai_link_decoding_read_seams(): array {

		return [
			"get_page (content)" => [PageService::class, "aiPageContentFields"],
			"get_module_entry" => [\BigTree\Services\SearchService::class, "getModuleEntryDetail"],
			"get_settings" => [SettingService::class, "aiDecodeSettingLinks"],
			// Not only for readability: TruncatedRead measures a written-back value
			// against the *decoded* stored value, so a read seam handing back tokens
			// puts every link-bearing field out of that refusal's reach.
			"list_module_entries" => [AutoModuleService::class, "aiListEntries"],
		];
	}

	/** E3: a read seam added without the decoder fails here. */
	function test_every_read_seam_decodes_link_tokens() {
		$missing = [];

		foreach (ai_link_decoding_read_seams() as $label => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$label}: {$method}'s source was read");

			if (strpos($body, "aiDenormalizeHtmlValue") === false) {
				$missing[] = $label;
			}
		}

		T::equals(implode(", ", $missing), "", "every AI read seam decodes link tokens");
	}

	/** E3: the decode runs before the cap, or a decoded link moves where the cut falls. */
	function test_link_decoding_precedes_the_length_cap() {
		foreach ([
			"get_page (content)" => [PageService::class, "aiPageContentFields"],
			"get_module_entry" => [\BigTree\Services\SearchService::class, "getModuleEntryDetail"],
			"list_module_entries" => [AutoModuleService::class, "aiListEntries"],
		] as $label => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);
			$decode = strpos($body, "aiDenormalizeHtmlValue");
			// The *last* cap in the body is the one that applies to the decoded scalar;
			// getModuleEntryDetail caps the array/JSON branch first, and that branch has
			// no markup to decode.
			$cap = strrpos($body, "mb_substr");

			T::ok($decode !== false && $cap !== false, "{$label} both decodes and caps");
			T::ok($decode < $cap, "{$label} decodes before it caps");
		}
	}

	// — E4: prompt and decline coverage —

	/** E4: the truncation rule is actually in the prompt the model reads. */
	function test_prompt_states_the_truncation_rule() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$prompt = (new \BigTree\Services\AIChatService())->systemPrompt(
			(object)["id" => 1, "level" => 2, "permissions" => []]
		);

		T::ok(strpos($prompt, "truncated") !== false, "the prompt uses the word truncated");
		T::ok(strpos($prompt, "content_truncated") !== false, "and names the page disclosure key");
		T::ok(strpos($prompt, "fields_truncated") !== false, "and the entry disclosure key");
		T::ok(
			strpos($prompt, "discard everything past the cut") !== false,
			"and states the consequence of writing one back"
		);
	}

	/** E4: the bulk wall has a decline line, so a bulk ask can't produce improvisation. */
	function test_bulk_changes_are_declined_in_words() {
		$lines = CapabilitySummary::outOfScope();
		$found = "";

		foreach ($lines as $capability => $instead) {
			if (stripos($capability, "many records") !== false) {
				$found = $capability . " → " . $instead;

				break;
			}
		}

		T::ok($found !== "", "outOfScope() names applying a change to many records at once");
		T::ok(
			stripos($found, "one change at a time") !== false,
			"and says the assistant proposes one change at a time"
		);
		T::ok(
			stripos($found, "bulk actions") !== false,
			"and steers to the admin's own bulk actions rather than only refusing"
		);
	}

	/** E4: the write tools' own descriptions warn against writing a truncated value back. */
	function test_write_tool_descriptions_warn_about_truncated_values() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$checked = 0;

		foreach ($registry->availableTools($developer) as $tool) {
			$name = $tool->name();

			if ($name !== "update_module_entry" && $name !== "update_page_content") {

				continue;
			}

			$definition = $tool->definition($developer);
			$argument = $name === "update_module_entry" ? "data" : "content";
			$description = (string)(
				$definition["function"]["parameters"]["properties"][$argument]["description"] ?? ""
			);

			T::ok(
				stripos($description, "truncated") !== false,
				"{$name}.{$argument} warns against writing a truncated value back"
			);
			$checked++;
		}

		T::equals($checked, 2, "both replace-semantics write tools were checked");
	}
