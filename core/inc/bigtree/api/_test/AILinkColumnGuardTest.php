<?php
	/**
	 * Audit #24 E1: the link-token machinery is enumerated by *column*, not by field
	 * type.
	 *
	 * Audit #10 built both halves of the link tokenizer — aiNormalizeHtmlValue on the
	 * way in, aiDenormalizeHtmlValue on the way out — and wired them into every schema
	 * -typed read and write across pages, entries, search and settings. Every
	 * assertion it left behind is keyed to `PageService::AI_LINK_BEARING_TYPES`, a
	 * *field type*. Two of the columns that hold links are not schema-typed fields at
	 * all — `bigtree_pages.external` and `bigtree_open_graph.image` — so there was no
	 * type for a schema-keyed test to match, no parity leg fired (REST had the
	 * identical hole), and `external` went untokenized for fourteen audits while the
	 * preview layer was being taught to decode a shape the writers had stopped
	 * producing.
	 *
	 * So this guard closes the class rather than the instance, in two legs:
	 *
	 *  1. **Discovery.** Every file under services/ (plus the three legacy files that
	 *     participate) mentioning `ipl://`, `irl://` or `{wwwroot}` must be classified
	 *     — as the home of a declared column's writer or reader, or with an explicit
	 *     "exempt: reason". A new file that starts handling a link token fails until
	 *     somebody decides which it is. A stale entry fails too.
	 *  2. **Contract.** For each declared column, every write path names a normalize
	 *     call and every AI read path names a denormalize call, checked against the
	 *     real method bodies. Dropping either leg from either path fails here.
	 *
	 * A hand-written list of columns is exactly what let `external` through, which is
	 * why leg 1 exists at all: the list below can be incomplete, but it cannot be
	 * incomplete *quietly*.
	 */

	use BigTree\Services\AI\FieldTypeDomain;

	/** The token prefixes a link-bearing column can hold, from the declaration itself. */
	function ai_link_token_literals(): array {

		return FieldTypeDomain::LINK_TOKEN_PREFIXES;
	}

	/**
	 * The columns whose stored value can be a link token, with the paths that write
	 * and read them.
	 *
	 * `writers` is every path that puts a value in the column — a normalize call is
	 * required in each. `ai_reads` is every seam that hands the value to the model —
	 * a denormalize call is required in each. Both are class::method pairs so the
	 * assertion reads the real source rather than a description of it.
	 *
	 * @return array<string,array{writers:array<string,array{0:string,1:string}>,ai_reads:array<string,array{0:string,1:string}>}>
	 */
	function ai_link_bearing_columns(): array {
		$pages = \BigTree\Services\PageService::class;
		$fourohfour = \BigTree\Services\FourOhFourService::class;

		return [
			// The nav entry that points somewhere else. Legacy makeIPLs it on both
			// write paths (admin.php:1637, :8837); no remaster writer did until audit
			// #24, so a link at a page on this site was stored hard and broke the next
			// time that page's route changed.
			"bigtree_pages.external" => [
				"writers" => [
					"live create" => [$pages, "performCreate"],
					"live update" => [$pages, "performUpdate"],
					"queued change" => [$pages, "pendingChangeFields"],
				],
				"ai_reads" => [
					"get_page" => [$pages, "aiPageDetailFields"],
					"get_page_tree" => [$pages, "aiPageTree"],
				],
			],
			// The social preview image. OpenGraphDomain::violation has accepted a token
			// since audit #22 because getOpenGraph untranslates one before rendering —
			// but nothing produced one, and detail() handed the stored value back raw.
			"bigtree_open_graph.image" => [
				"writers" => [
					"page open graph" => [$pages, "syncOpenGraph"],
				],
				"ai_reads" => [
					"get_page" => [$pages, "loadOpenGraph"],
					"get_module_entry" => [\BigTree\Services\AI\OpenGraphDomain::class, "detail"],
				],
			],
			// A redirect destination, which has tokenized since it shipped — the
			// positive control the audit reasoned from, asserted here so it stays one.
			"bigtree_404s.redirect_url" => [
				"writers" => [
					"create301" => [$fourohfour, "create301"],
				],
				"ai_reads" => [
					"list_404s" => [$fourohfour, "aiList404s"],
					"create_redirect card" => [$fourohfour, "aiValidateRedirectCreate"],
				],
			],
		];
	}

	/** The calls that count as putting a value into its stored, tokenized form. */
	function ai_link_normalize_calls(): array {

		return ["makeIPL(", "autoIPL(", "storedExternalLink(", "storedImage("];
	}

	/** The calls that count as turning a stored value back into something readable. */
	function ai_link_denormalize_calls(): array {

		return ["untokenize(", "aiDenormalizeHtmlValue(", "PreviewValue::forHuman("];
	}

	/**
	 * Files that mention a link token but own no column, with the reason.
	 *
	 * Keyed by path relative to core/inc/bigtree/. A reason is required, and a stale
	 * entry — a file that no longer mentions a token — fails, so this cannot become a
	 * place things are quietly parked.
	 *
	 * @return array<string,string>
	 */
	function ai_link_token_file_exemptions(): array {

		return [
			"services/LinkService.php" => "the implementation: makeIPL and untokenize are what the columns call",
			"services/AI/FieldTypeDomain.php" => "declares the prefixes and the `link` field shape rule",
			"services/AI/PreviewValue.php" => "the proposal card's decoder, applied to every previewed value",
			"services/AutoModuleService.php" => "entry columns are schema-typed, so AI_LINK_BEARING_TYPES covers them",
			"services/SettingService.php" => "settings are schema-typed too, same coverage",
			"services/PendingChangeService.php" => "renders a queued change's diff through the preview decoder",
			"services/ResourceAllocationService.php" => "reads irl:// out of stored values to build allocations; writes none",
			"services/IntegrityService.php" => "the link checker — it resolves tokens to test them, and stores nothing",
			"services/ImageService.php" => "resolves an irl:// to a file on disk for processing",
			"cms.php" => "the front end: getInternalPageLink / replaceInternalPageLinks are the untranslate side",
			"admin.php" => "legacy writers, which have always tokenized (createPage, updatePage, handleOpenGraph)",
		];
	}

	/** Every file in the scan that mentions one of the token literals. */
	function ai_link_token_files(): array {
		$root = SERVER_ROOT . "core/inc/bigtree/";
		$candidates = array_merge(
			glob($root . "services/*.php") ?: [],
			glob($root . "services/AI/*.php") ?: [],
			[$root . "cms.php", $root . "admin.php", $root . "auto-modules.php"]
		);
		$found = [];

		foreach ($candidates as $file) {
			if (!is_readable($file)) {

				continue;
			}

			$source = (string)file_get_contents($file);

			foreach (ai_link_token_literals() as $literal) {
				if (strpos($source, $literal) !== false) {
					$found[] = str_replace($root, "", $file);

					break;
				}
			}
		}

		sort($found);

		return $found;
	}

	/** The files the declared column map points at, relative to core/inc/bigtree/. */
	function ai_link_declared_files(): array {
		$root = SERVER_ROOT . "core/inc/bigtree/";
		$files = [];

		foreach (ai_link_bearing_columns() as $column) {
			foreach (array_merge($column["writers"], $column["ai_reads"]) as [$class, $method]) {
				$file = (new ReflectionMethod($class, $method))->getFileName();

				if ($file) {
					$files[str_replace($root, "", $file)] = true;
				}
			}
		}

		return $files;
	}

	/**
	 * E1, leg 1: every file that handles a link token is accounted for.
	 *
	 * This is the leg that closes the class. `external` was invisible to fourteen
	 * audits because nothing enumerated the *places* tokens are handled — only the
	 * field types that carry them.
	 */
	function test_every_file_handling_a_link_token_is_classified() {
		$files = ai_link_token_files();
		$declared = ai_link_declared_files();
		$exempt = ai_link_token_file_exemptions();

		T::ok(count($files) >= 10, "the token scan found the files that handle links (" . count($files) . ")");

		$unclassified = [];

		foreach ($files as $file) {
			if (isset($declared[$file]) || isset($exempt[$file])) {

				continue;
			}

			$unclassified[] = $file;
		}

		T::equals(
			implode(", ", $unclassified),
			"",
			"every file handling a link token owns a declared column or carries an exemption reason"
		);

		$stale = [];

		foreach (array_keys($exempt) as $file) {
			if (!in_array($file, $files, true)) {
				$stale[] = $file;
			}
		}

		T::equals(implode(", ", $stale), "", "and no exemption names a file that no longer handles one");
	}

	/** E1, leg 2a: every write path for a link-bearing column tokenizes. */
	function test_every_link_column_writer_normalizes() {
		$missing = [];

		foreach (ai_link_bearing_columns() as $column => $paths) {
			foreach ($paths["writers"] as $label => [$class, $method]) {
				$body = ai_surface_method_body($class, $method);
				T::ok($body !== "", "{$column}: {$method} source was read");

				$normalizes = false;

				foreach (ai_link_normalize_calls() as $call) {
					if (strpos($body, $call) !== false) {
						$normalizes = true;

						break;
					}
				}

				if (!$normalizes) {
					$missing[] = "{$column} ({$label})";
				}
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"every writer of a link-bearing column stores the value in token form"
		);
	}

	/** E1, leg 2b: every AI read of one decodes it again. */
	function test_every_link_column_ai_read_denormalizes() {
		$missing = [];

		foreach (ai_link_bearing_columns() as $column => $paths) {
			foreach ($paths["ai_reads"] as $label => [$class, $method]) {
				$body = ai_surface_method_body($class, $method);
				T::ok($body !== "", "{$column}: {$method} source was read");

				$decodes = false;

				foreach (ai_link_denormalize_calls() as $call) {
					if (strpos($body, $call) !== false) {
						$decodes = true;

						break;
					}
				}

				if (!$decodes) {
					$missing[] = "{$column} ({$label})";
				}
			}
		}

		T::equals(
			implode(", ", $missing),
			"",
			"every AI read of a link-bearing column hands the model a resolvable value"
		);
	}

	/**
	 * The decoder's own contract, which is what makes a dangling token safe to show.
	 *
	 * getInternalPageLink returns null for a token whose page has been deleted, so a
	 * bare decode would turn a dangling link into "" — which reads as "this page has
	 * no link" and would clear a real one if the value were written straight back.
	 */
	function test_the_link_decoder_never_empties_a_non_empty_value() {
		$dangling = "ipl://999999999//W10=////";

		T::equals(
			\BigTree\Services\LinkService::untokenize($dangling),
			$dangling,
			"a token whose target is gone comes back as the token, not as an empty string"
		);
		T::equals(
			\BigTree\Services\LinkService::untokenize("https://example.com/thing/"),
			"https://example.com/thing/",
			"and a plain absolute URL is returned untouched, so decoding is idempotent"
		);
		T::equals(\BigTree\Services\LinkService::untokenize(""), "", "an empty value stays empty");
	}
