<?php
	/**
	 * Audit #15 Part B: the preview ↔ proposal-card contract.
	 *
	 * Every other seam in the two-phase design has a contract test. The tool
	 * definition ↔ backend seam has AIArgShapeContractTest; the tool ↔ route seam has
	 * AIRouteFamilyContractTest and AIInverseSurfaceGuardTest; the value ↔ column seam
	 * has AIStorageBoundaryTest. The `preview` array a validator returns ↔
	 * spa/src/components/ai/ProposalCard.tsx seam had nothing at all — and it is the
	 * last surface before the write happens and the only one a human reads.
	 *
	 * Every finding in audit #15 Part A is what an unguarded seam produces: keys
	 * written specifically to be seen that render nowhere (`new_tags`,
	 * `unsettable_columns`), and values rendered in a form the approver cannot check
	 * (`ipl://cGFnZXM6NDI=` on both sides of a link diff, `Photo: 482`, `["12","15"]`).
	 *
	 * The legs, in the order the audit lists them:
	 *
	 *  1. Every key any validator emits into a preview is classified: rendered as a
	 *     row, rendered as a block, or hidden *with a reason recorded here*. A new
	 *     preview key fails this test until someone decides which.
	 *  2. A shape leg: any key whose value can be an array or object must be one the
	 *     card handles specially, because ProposalCard's generic scan drops arrays and
	 *     objects silently (`continue` in previewRows). This is the leg A3 failed.
	 *  3. A link-token leg: every builder that can carry a value from a link-bearing
	 *     field must resolve it through PreviewValue, which decodes the token before
	 *     the cap. A sixth preview builder fails until it is classified.
	 *
	 * Both sides are read as text — the service sources through reflection, the card
	 * through the SPA source — for the reason AIStorageBoundaryTest parses
	 * routes/pages.php as text: the two cannot drift if neither one is trusted to
	 * describe itself.
	 */

	use BigTree\Services\AI\PreviewValue;

	/** The service files that can stage a proposal. */
	function ai_preview_service_files(): array {
		$root = dirname(__DIR__, 2) . "/services";

		return array_merge(
			glob($root . "/*.php") ?: [],
			glob($root . "/AI/*.php") ?: [],
			glob($root . "/AI/Tools/*.php") ?: []
		);
	}

	/**
	 * Every key emitted into a proposal preview, mapped to where it comes from.
	 *
	 * Both forms are parsed because both are in use, and the second is where audit
	 * #15's A3 lived: a `"preview" => [ … ]` / `$preview = [ … ]` literal, and a
	 * `$preview["…"] =` assignment made after the literal.
	 *
	 * @return array<string,list<string>> key => the files that emit it
	 */
	function ai_preview_emitted_keys(): array {
		$keys = [];

		foreach (ai_preview_service_files() as $file) {
			$source = ai_preview_strip_comments((string)file_get_contents($file));
			$name = basename($file);

			if (preg_match_all('/(?:"preview"\s*=>\s*\[|\$preview\s*=\s*\[)/', $source, $matches, PREG_OFFSET_CAPTURE)) {
				foreach ($matches[0] as $hit) {
					foreach (ai_preview_literal_keys($source, $hit[1] + strpos($hit[0], "[")) as $key) {
						$keys[$key][] = $name;
					}
				}
			}

			if (preg_match_all('/\$preview\["([a-zA-Z0-9_]+)"\]\s*=[^=]/', $source, $assigned)) {
				foreach ($assigned[1] as $key) {
					$keys[$key][] = $name;
				}
			}
		}

		foreach ($keys as $key => $files) {
			$keys[$key] = array_values(array_unique($files));
		}

		ksort($keys);

		return $keys;
	}

	/**
	 * The top-level keys of one array literal, starting at its opening bracket. Keys
	 * nested inside a value (a `changes` map's own entries, say) are deliberately not
	 * collected: the card's contract is with the top level.
	 *
	 * @return list<string>
	 */
	function ai_preview_literal_keys(string $source, int $open): array {
		$keys = [];
		$depth = 0;
		$length = strlen($source);

		for ($i = $open; $i < $length; $i++) {
			$character = $source[$i];

			if ($character === "[") {
				$depth++;

				continue;
			}

			if ($character === "]") {
				$depth--;

				if ($depth === 0) {

					return $keys;
				}

				continue;
			}

			if ($depth === 1 && $character === '"'
				&& preg_match('/^"([a-zA-Z0-9_]+)"\s*=>/', substr($source, $i, 80), $match)) {
				$keys[] = $match[1];
			}
		}

		return $keys;
	}

	/**
	 * Drop whole-line `//` comments before parsing. A comment inside a preview literal
	 * that happened to quote a key would otherwise read as an emitted one; only
	 * full-line comments are stripped, so a `"resource://"` in a value survives.
	 */
	function ai_preview_strip_comments(string $source): string {

		return (string)preg_replace('/^[ \t]*\/\/.*$/m', "", $source);
	}

	/** The card's source. */
	function ai_proposal_card_source(): string {
		$path = dirname(__DIR__, 5) . "/spa/src/components/ai/ProposalCard.tsx";

		return is_readable($path) ? (string)file_get_contents($path) : "";
	}

	/**
	 * The keys ProposalCard.tsx names in its HIDDEN_KEYS set — parsed from the card
	 * rather than restated here, so the two cannot drift.
	 *
	 * @return list<string>
	 */
	function ai_card_hidden_keys(): array {
		$source = ai_proposal_card_source();

		if (!preg_match('/const HIDDEN_KEYS = new Set\(\[(.*?)\]\);/s', $source, $match)) {

			return [];
		}

		preg_match_all('/"([a-z_]+)"/', (string)preg_replace('/^\s*\/\/.*$/m', "", $match[1]), $keys);

		return array_values(array_unique($keys[1]));
	}

	/**
	 * The keys the card renders through a branch of its own — a row shape
	 * `previewRows` special-cases, or a dedicated block in the component body.
	 *
	 * Restated here with what each one renders as, because that is the decision the
	 * contract is about; the legs below check every one of them is really referenced
	 * by the card, so a renderer deleted on the SPA side fails here.
	 *
	 * @return array<string,string> key => how the card renders it
	 */
	function ai_card_special_keys(): array {

		return [
			// previewRows shapes.
			"changes" => "a diff map, one before/after row per entry",
			"fields" => "a field list, one row per field",
			"tags" => "the Tags row",
			"new_tags" => "the New tags row (audit #15 A2)",
			"from" => "the Merging row when it is a list, otherwise the before side of the top-level diff",
			"into" => "the Into row",
			"to" => "the after side of the top-level diff",
			"field" => "the label of the top-level diff row",
			// Dedicated blocks: keys that carry a consequence rather than a value.
			"destructive" => "the permanent-deletion warning",
			"incomplete_required" => "the needs-a-person-afterwards warning",
			"warning" => "the warning block",
			"publishes_draft" => "the publishes-someone's-draft warning",
			"content_lock" => "the someone-has-this-open warning",
			"depends_on" => "the approve-these-in-order note",
			"ignored" => "the ignored (not applicable) line",
			"remaining_setup" => "the still-to-do-by-hand list",
			"unsettable_columns" => "the stored-empty line (audit #15 A3)",
		];
	}

	/**
	 * Why each hidden key is hidden. Exactly the tool-or-decline shape
	 * AIRouteFamilyContractTest uses, for the same reason: a key that renders nowhere
	 * is a decision, and a decision with no reason written down is the bug audit #15
	 * A2 and A3 both are.
	 *
	 * @return array<string,string> key => reason
	 */
	function ai_preview_intentionally_hidden(): array {

		return [
			"action" => "the tool that staged the card; the summary already says what it will do",
			"changes" => "rendered as rows by previewRows, never as an anonymous key/value",
			"fields" => "rendered as rows by previewRows",
			"tags" => "rendered as its own row",
			"existing_tags" => "`tags` minus `new_tags`, both of which are rendered — a third row repeating them is noise",
			"mode" => "published/pending, which the summary's mode note spells out in a sentence",
			"page_id" => "an internal id; the card names the page by title, and links to it after approval",
			"entry_id" => "an internal id; the card names the entry by its label",
			"change_id" => "an internal id; the card names the pending change by title",
			"user_id" => "an internal id; the card names the user by user_label",
			"module_id" => "an internal id; the card names the module by name",
			"destructive" => "rendered as the deletion warning, not as \"Destructive: Yes\"",
			"target" => "the kind of thing being tagged (page/entry), implied by target_title and the summary",
			"target_title" => "already the subject of the summary sentence",
			"mine" => "whether the pending change is the actor's own — the summary says whose it is",
			"is_new_item" => "whether the queued change creates or edits; the summary's consequence sentence says which",
			"template_changed" => "a flag behind the template row, which shows the change itself",
			"sends_invite_email" => "stated in the `note` the summary carries",
			"grants_permissions" => "stated in the `note` the summary carries",
			"note" => "folded into the summary; as a row it was single-line truncated in a 440px panel",
			"warning" => "rendered as the warning block",
			"remaining_setup" => "rendered as the still-to-do list",
			"ignored" => "rendered as the ignored line",
			"publishes_draft" => "rendered as the publishes-someone's-draft warning",
			"content_lock" => "rendered as the someone-has-this-open warning",
			"incomplete_required" => "rendered as the needs-a-person warning",
			"depends_on" => "rendered as the approve-in-order note",
			"unsettable_columns" => "rendered as the stored-empty line",
		];
	}

	/**
	 * Every emitted key's value shape. `structured` means the value can be an array or
	 * an object, which ProposalCard's generic scan drops on the floor — so a
	 * structured key has to be one the card handles itself.
	 *
	 * The map covers the emitted set exactly, in both directions: a new preview key
	 * fails this test until it is classified here, and a key that stops being emitted
	 * has to be taken out.
	 *
	 * @return array<string,string> key => scalar|structured
	 */
	function ai_preview_key_shapes(): array {

		return [
			"action" => "scalar",
			"alerts" => "scalar",
			"callouts" => "scalar",
			"change_id" => "scalar",
			"changes" => "structured",
			"class" => "scalar",
			"company" => "scalar",
			"content_lock" => "structured",
			"creates_file" => "scalar",
			"creates_table" => "scalar",
			"daily_digest" => "scalar",
			"depends_on" => "scalar",
			"descendants_affected" => "scalar",
			"description" => "scalar",
			"destination" => "scalar",
			"destructive" => "scalar",
			"display_field" => "scalar",
			"email" => "scalar",
			"entry" => "scalar",
			"entry_id" => "scalar",
			"existing_tags" => "structured",
			"expire_at" => "scalar",
			"external" => "scalar",
			"fields" => "structured",
			"flag" => "scalar",
			"form" => "scalar",
			// A list on merge_tags (the tags being consumed) and a scalar on
			// update_setting / set_module_entry_flag (the value being replaced).
			"from" => "structured",
			"from_path" => "scalar",
			"grants_permissions" => "scalar",
			"group" => "scalar",
			"icon" => "scalar",
			"id" => "scalar",
			"ignored" => "structured",
			"in_nav" => "scalar",
			"incomplete_required" => "structured",
			"into" => "scalar",
			"is_draft" => "scalar",
			"is_new_item" => "scalar",
			"leaves_navigation" => "scalar",
			"level" => "scalar",
			"max_age" => "scalar",
			"meta_description" => "scalar",
			"meta_keywords" => "scalar",
			"mine" => "scalar",
			"mode" => "scalar",
			"module" => "scalar",
			"module_id" => "scalar",
			"moves_out_of" => "scalar",
			"name" => "scalar",
			"nav_title" => "scalar",
			"new_parent_id" => "scalar",
			"new_parent_title" => "scalar",
			"new_tags" => "structured",
			"new_window" => "scalar",
			"note" => "scalar",
			"open_graph" => "scalar",
			"page_id" => "scalar",
			"page_reference" => "scalar",
			"page_title" => "scalar",
			"parent_id" => "scalar",
			"parent_title" => "scalar",
			"path" => "scalar",
			"publish_at" => "scalar",
			"publishes_draft" => "structured",
			"records_moved" => "scalar",
			"remaining_setup" => "structured",
			"replaces" => "scalar",
			"revision_date" => "scalar",
			"revision_id" => "scalar",
			"route" => "scalar",
			"routed" => "scalar",
			"save_as_draft" => "scalar",
			"sends_invite_email" => "scalar",
			"seo_invisible" => "scalar",
			"site" => "scalar",
			"source" => "scalar",
			"table" => "scalar",
			"tagged_records" => "scalar",
			"tagging" => "scalar",
			"tags" => "structured",
			"target" => "scalar",
			"target_title" => "scalar",
			"template" => "scalar",
			"template_changed" => "scalar",
			"timezone" => "scalar",
			"title" => "scalar",
			"to" => "scalar",
			"to_path" => "scalar",
			"type" => "scalar",
			"unsettable_columns" => "structured",
			"user_id" => "scalar",
			"user_label" => "scalar",
			"view" => "scalar",
			"view_type" => "scalar",
			"warning" => "scalar",
		];
	}

	/**
	 * Every method in the service layer that renders a value for a proposal card,
	 * discovered by name (ai*Preview* / ai*Diff*) rather than listed, and classified.
	 *
	 * `resolves` — must call PreviewValue::forHuman, which decodes link tokens and
	 * names references and relations before the cap.
	 * `delegates:<method>` — hands each value to another builder on this list.
	 * `n/a: <reason>` — cannot carry a content value.
	 *
	 * @return array<string,string> Class::method => classification
	 */
	function ai_preview_value_builders(): array {

		return [
			"BigTree\\Services\\PageService::aiPreviewResourceContent" => "resolves",
			"BigTree\\Services\\PageService::aiPreviewScalarValue" => "resolves",
			"BigTree\\Services\\AutoModuleService::aiPreviewEntryData" => "delegates:aiPreviewScalar",
			"BigTree\\Services\\AutoModuleService::aiPreviewScalar" => "resolves",
			"BigTree\\Services\\PendingChangeService::aiDiffValue" => "resolves",
			"BigTree\\Services\\PendingChangeService::aiChangeDiff" => "delegates:aiDiffValue",
			"BigTree\\Services\\SettingService::aiSettingPreviewValue" => "resolves",
			"BigTree\\Services\\PageService::aiSchedulePreviewValue"
				=> "n/a: a resolved timestamp plus the phrase the model asked for, never a stored field value",
			"BigTree\\Services\\TemplateService::aiPreviewFields"
				=> "n/a: template field *definitions* (id, title, type) — there is no content on a create_template card",
			"BigTree\\Services\\TemplateService::aiTemplateFieldDiff"
				=> "n/a: the same field definitions, diffed",
			"BigTree\\Services\\CalloutService::aiCalloutFieldDiff"
				=> "n/a: callout field definitions, diffed — a callout's *content* lives on a page, not here",
		];
	}

	/**
	 * Every service method that builds a `"from" => …, "to" => …` pair — the
	 * before/after row shape ProposalCard renders — and what it does with the value.
	 *
	 * `resolves` — hands its values to PreviewValue, directly or through one of the
	 * thin wrappers, so a stored token reaches the approver decoded.
	 * `n/a: <reason>` — the pair carries something that is not a stored field value.
	 *
	 * This is the leg the builder scan above structurally could not cover. That scan
	 * finds methods *named* ai*Preview* / ai*Diff*, and a diff a validator builds inline
	 * in the middle of itself has no such name: update_page's did not, so it went on
	 * showing `ipl://cGFnZXM6NDI=` on the `from` side of an `external` change after
	 * every named builder had been fixed. A new inline diff fails here until someone
	 * classifies it.
	 *
	 * Methods already classified in ai_preview_value_builders() are skipped rather
	 * than restated, so the two maps cannot disagree about the same method.
	 *
	 * @return array<string,string> Class::method => classification
	 */
	function ai_preview_diff_constructors(): array {

		return [
			"BigTree\\Services\\PageService::aiValidatePageUpdate" => "resolves",
			"BigTree\\Services\\PageService::aiValidatePageContentUpdate" => "resolves",
			"BigTree\\Services\\PageService::aiValidateRevisionRestore" => "resolves",
			"BigTree\\Services\\SettingService::aiValidateSettingUpdate" => "resolves",
			// The pair here is the *payload*'s own keys — the source path and the
			// destination the model wrote, neither of them a stored value. The one
			// stored value on this card is `replaces`, read out of bigtree_404s, which
			// set404Redirect writes through autoIPL; it goes through PreviewValue, and
			// classifying this method as resolving is what keeps that call in place.
			"BigTree\\Services\\FourOhFourService::aiValidateRedirectCreate" => "resolves",
			"BigTree\\Services\\PageService::aiPageOpenGraph"
				=> "n/a: an internal helper's {from,to,stored} return shape rather than a preview row — its "
					. "values reach the card through aiValidatePageUpdate's diff, which resolves",
			"BigTree\\Services\\FourOhFourService::aiCreateRedirect"
				=> "n/a: the approval *result* read back off the row, not a card — the model gets the stored "
					. "shape here, the same shape the redirect read seams return",
			"BigTree\\Services\\AutoModuleService::aiValidateEntryFlag"
				=> "n/a: an entry flag's on/off state — a boolean the card renders as Yes/No, with no text in it",
			"BigTree\\Services\\CalloutService::aiValidateCalloutUpdate"
				=> "n/a: a callout's *definition* (name, description, level, display field, group) — a callout's "
					. "content lives on a page, not here",
			"BigTree\\Services\\ModuleService::aiValidateModuleUpdate"
				=> "n/a: a module's definition (name, group, icon, class), none of which is field content",
			"BigTree\\Services\\TemplateService::aiValidateTemplateUpdate"
				=> "n/a: a template's definition (name, level, field count)",
			"BigTree\\Services\\TagService::aiValidateTagRename"
				=> "n/a: a tag's own name, which is stored as written",
			"BigTree\\Services\\UserService::aiValidateUserUpdate"
				=> "n/a: a user record (name, email, digest flag, alert list) — no field content on this card",
			"BigTree\\Services\\AI\\Tools\\CreateRedirectTool::definition"
				=> "n/a: the tool's JSON argument schema — `from` and `to` are argument names there, not rows",
		];
	}

	/**
	 * Every Class::method in the service layer that emits both a `"from" =>` and a
	 * `"to" =>` literal, found by walking the source rather than by name.
	 *
	 * @return list<string>
	 */
	function ai_preview_found_diff_constructors(): array {
		$found = [];

		foreach (ai_preview_service_files() as $file) {
			$source = ai_preview_strip_comments((string)file_get_contents($file));
			$class = "BigTree\\Services\\" . basename($file, ".php");

			if (preg_match('/^\s*namespace\s+([^;]+);/m', $source, $namespace)) {
				$class = trim($namespace[1]) . "\\" . basename($file, ".php");
			}

			$method = "";
			$sides = [];

			foreach (explode("\n", $source) as $line) {
				// Named functions only: an anonymous one attributes its rows to the
				// method enclosing it, which is the method being classified anyway.
				if (preg_match('/function\s+([A-Za-z0-9_]+)\s*\(/', $line, $match)) {
					$method = $match[1];
				}

				if ($method === "") {

					continue;
				}

				if (preg_match('/"from"\s*=>/', $line)) {
					$sides[$method]["from"] = true;
				}

				if (preg_match('/"to"\s*=>/', $line)) {
					$sides[$method]["to"] = true;
				}
			}

			foreach ($sides as $method => $seen) {
				if (!empty($seen["from"]) && !empty($seen["to"])) {
					$found[] = $class . "::" . $method;
				}
			}
		}

		return array_values(array_unique($found));
	}

	/**
	 * Leg 1: every key any validator emits is rendered as a row, rendered as a block,
	 * or hidden with a reason recorded.
	 */
	function test_every_emitted_preview_key_is_classified() {
		$emitted = ai_preview_emitted_keys();
		$hidden = ai_card_hidden_keys();
		$special = ai_card_special_keys();
		$reasons = ai_preview_intentionally_hidden();

		T::ok(count($emitted) > 50, "the preview key scan found the service layer's keys (" . count($emitted) . ")");
		T::ok($hidden !== [], "ProposalCard's HIDDEN_KEYS set was parsed");

		// A hidden key with no reason written down is the decision audit #15 A2 and A3
		// were both made of. Checked in both directions so a stale reason goes too.
		$unexplained = array_values(array_diff($hidden, array_keys($reasons)));
		$stale = array_values(array_diff(array_keys($reasons), $hidden));

		T::equals(
			implode(", ", $unexplained),
			"",
			"every key ProposalCard hides has its reason recorded in ai_preview_intentionally_hidden()"
		);
		T::equals(
			implode(", ", $stale),
			"",
			"and every recorded reason names a key the card really hides"
		);

		// Each renderer the contract claims exists really is in the card.
		$card = ai_proposal_card_source();
		$missing_renderer = [];

		foreach ($special as $key => $rendered) {
			if (strpos($card, $key) === false) {
				$missing_renderer[] = "{$key} (claimed: {$rendered})";
			}
		}

		T::equals(
			implode(", ", $missing_renderer),
			"",
			"every key the contract says the card renders specially is referenced by ProposalCard.tsx"
		);

		// And the emitted set is fully classified: special, hidden, or a plain row.
		$shapes = ai_preview_key_shapes();
		$unclassified = array_values(array_diff(array_keys($emitted), array_keys($shapes)));
		$gone = array_values(array_diff(array_keys($shapes), array_keys($emitted)));

		T::equals(
			implode(", ", $unclassified),
			"",
			"every emitted preview key is classified in ai_preview_key_shapes()"
		);
		T::equals(
			implode(", ", $gone),
			"",
			"and no classification names a key nothing emits any more"
		);
	}

	/**
	 * Leg 2: the shape leg. ProposalCard's generic scan skips arrays and objects —
	 * `continue` in previewRows — so a structured key the card doesn't handle itself
	 * renders nowhere at all, silently. This is the leg audit #15 A3 failed:
	 * `unsettable_columns` was an array with no renderer for as long as it existed.
	 */
	function test_structured_preview_keys_are_handled_by_the_card() {
		$emitted = ai_preview_emitted_keys();
		$shapes = ai_preview_key_shapes();
		$special = ai_card_special_keys();
		$hidden = ai_card_hidden_keys();
		$dropped = [];

		foreach ($emitted as $key => $files) {
			if (($shapes[$key] ?? "scalar") !== "structured") {

				continue;
			}

			if (isset($special[$key]) || in_array($key, $hidden, true)) {

				continue;
			}

			$dropped[] = "{$key} (emitted by " . implode(", ", $files) . ")";
		}

		T::equals(
			implode(", ", $dropped),
			"",
			"every array/object preview key has a renderer of its own, rather than being dropped by the generic scan"
		);

		// The card's array branches and the shape map have to agree: a key the card
		// renders as a list that the map calls scalar means one of the two is wrong.
		$card = ai_proposal_card_source();

		foreach (["tags", "new_tags", "ignored", "remaining_setup", "incomplete_required", "unsettable_columns"] as $key) {
			T::ok(
				strpos($card, "Array.isArray(proposal.preview.{$key})") !== false
					|| strpos($card, "Array.isArray(preview.{$key})") !== false,
				"ProposalCard reads `{$key}` as the list it is"
			);
			T::equals($shapes[$key] ?? "", "structured", "`{$key}` is classified as structured");
		}
	}

	/**
	 * Leg 3: the link-token leg (audit #15 A1).
	 *
	 * Audit #10 B3 built aiDenormalizeHtmlValue and applied it at five read seams and
	 * zero preview seams, which inverts the protection backwards: its own docblock
	 * names the proposal diff as the place a mangled token cannot be caught. Every
	 * builder that can carry a field value now resolves through PreviewValue, and a
	 * sixth builder fails this leg until it is classified.
	 */
	function test_every_preview_value_builder_is_classified() {
		$classified = ai_preview_value_builders();
		$found = [];

		foreach (ai_preview_service_files() as $file) {
			$source = (string)file_get_contents($file);
			$class = "BigTree\\Services\\" . basename($file, ".php");

			if (preg_match('/^\s*namespace\s+([^;]+);/m', $source, $namespace)) {
				$class = trim($namespace[1]) . "\\" . basename($file, ".php");
			}

			if (preg_match_all('/function\s+(ai[A-Za-z0-9_]*(?:Preview|Diff)[A-Za-z0-9_]*)\s*\(/', $source, $methods)) {
				foreach ($methods[1] as $method) {
					$found[] = $class . "::" . $method;
				}
			}
		}

		$found = array_values(array_unique($found));
		$unclassified = array_values(array_diff($found, array_keys($classified)));
		$gone = array_values(array_diff(array_keys($classified), $found));

		T::equals(
			implode(", ", $unclassified),
			"",
			"every preview/diff value builder in the service layer is classified (a new one fails until it is)"
		);
		T::equals(implode(", ", $gone), "", "and every classification names a builder that still exists");

		$wrong = [];

		foreach ($classified as $reference => $classification) {
			[$class, $method] = explode("::", $reference, 2);
			$body = ai_surface_method_body($class, $method);

			if ($body === "") {
				$wrong[] = "{$reference} (source unreadable)";

				continue;
			}

			if ($classification === "resolves" && strpos($body, "PreviewValue::forHuman") === false) {
				$wrong[] = "{$reference} (claims to resolve, never calls PreviewValue::forHuman)";

				continue;
			}

			if (strpos($classification, "delegates:") === 0) {
				$delegate = substr($classification, strlen("delegates:"));

				if (strpos($body, "\$this->{$delegate}(") === false) {
					$wrong[] = "{$reference} (claims to delegate to {$delegate}, never calls it)";
				}
			}
		}

		T::equals(implode(", ", $wrong), "", "every builder does what its classification says");

		// The resolver itself: the whole leg rests on this one call.
		$resolver = ai_surface_method_body(PreviewValue::class, "forHuman");
		T::ok(
			strpos($resolver, "aiDenormalizeHtmlValue") !== false,
			"PreviewValue::forHuman decodes link tokens (the inverse audit #10 B3 built, at the seam it was missing from)"
		);
	}

	/**
	 * Leg 4: the inline-diff leg.
	 *
	 * The leg above finds builders by name, and a validator that assembles its own
	 * before/after rows in the middle of itself has no name to find. update_page was
	 * exactly that: `$diff[$field] = ["from" => $old, "to" => $new]`, with `external`
	 * among the fields it diffs and makeIPL storing an internal link as `ipl://…`, so
	 * the approver was shown a base64 blob on the `from` side of the one row that
	 * says where the page currently points.
	 */
	function test_every_inline_diff_row_is_classified() {
		$classified = ai_preview_diff_constructors();
		$builders = ai_preview_value_builders();
		$resolvers = [
			"PreviewValue::forHuman",
			"aiPreviewScalarValue",
			"aiPreviewScalar",
			"aiDiffValue",
			"aiSettingPreviewValue",
		];

		// A method classified in the builder map is classified; restating it here would
		// let the two maps disagree about the same code.
		$found = array_values(array_filter(
			ai_preview_found_diff_constructors(),
			function (string $reference) use ($builders): bool {

				return !isset($builders[$reference]);
			}
		));

		T::ok($found !== [], "the inline diff scan found the service layer's from/to pairs (" . count($found) . ")");

		$unclassified = array_values(array_diff($found, array_keys($classified)));
		$gone = array_values(array_diff(array_keys($classified), $found));

		T::equals(
			implode(", ", $unclassified),
			"",
			"every method building a from/to preview row is classified (a new inline diff fails until it is)"
		);
		T::equals(implode(", ", $gone), "", "and every classification names a method that still builds one");

		$wrong = [];

		foreach ($classified as $reference => $classification) {
			if ($classification !== "resolves") {

				continue;
			}

			[$class, $method] = explode("::", $reference, 2);
			$body = ai_surface_method_body($class, $method);
			$resolved = false;

			foreach ($resolvers as $resolver) {
				if (strpos($body, $resolver) !== false) {
					$resolved = true;

					break;
				}
			}

			if (!$resolved) {
				$wrong[] = "{$reference} (claims to resolve, calls none of: " . implode(", ", $resolvers) . ")";
			}
		}

		T::equals(implode(", ", $wrong), "", "every inline diff that claims to resolve really does");
	}

	/**
	 * A1, at the value: a token reaches the card decoded, and the cap is applied after
	 * the decoding rather than before it — the reason aiDenormalizeHtmlValue's own
	 * docblock gives, since a hard link is longer than the token it replaces.
	 */
	function test_link_tokens_are_decoded_for_the_approver() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$page_id = (int)SQL::fetchSingle("SELECT id FROM bigtree_pages WHERE parent = 0 ORDER BY id ASC LIMIT 1");

		if ($page_id < 1) {

			return;
		}

		$token = "ipl://" . base64_encode("pages:" . $page_id);
		$markup = '<p>See <a href="' . $token . '">our pricing</a>.</p>';
		$rendered = PreviewValue::forHuman($markup, ["type" => "html"]);

		T::ok(strpos($rendered, "ipl://") === false, "an internal link previews as a link, not as base64");
		T::ok(strpos($rendered, "our pricing") !== false, "and the prose around it is untouched");

		// A `link` field holds the bare token as its whole value.
		$bare = PreviewValue::forHuman($token, ["type" => "link"]);
		T::ok(strpos($bare, "ipl://") === false, "a link field's whole value is decoded too");

		T::ok(
			mb_strlen(PreviewValue::forHuman(str_repeat("a", 5000))) <= PreviewValue::CAP,
			"a long value is still capped"
		);

		// The other shape autoIPL writes, and the one the two inline diffs above depend
		// on: a same-site URL is stored as a `{wwwroot}` token, not an `ipl://` one.
		$rooted = PreviewValue::forHuman("{wwwroot}pricing/");

		T::ok(strpos($rooted, "{wwwroot}") === false, "a relative root previews as the site's real URL");
		T::ok(strpos($rooted, "pricing/") !== false, "and keeps the path it points at");
	}

	/**
	 * A1, for references: the card said `Photo: 482`, which named a row the approver
	 * cannot look up — while search_files, which the *model* used to pick it, resolves
	 * the name server-side already.
	 */
	function test_reference_values_preview_as_the_file_they_name() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		$row = SQL::fetch("SELECT id, name, file FROM bigtree_resources WHERE name != '' ORDER BY id ASC LIMIT 1");
		$admin = (object)["id" => 1, "level" => 2, "permissions" => []];

		if (is_array($row)) {
			$rendered = PreviewValue::forHuman((string)$row["id"], ["type" => "image-reference"], $admin);

			T::ok(
				strpos($rendered, (string)$row["name"]) !== false,
				"a reference previews as the file it names, not as a bare id"
			);
			T::ok(
				strpos($rendered, "#" . (int)$row["id"]) !== false,
				"and keeps the id, which is what the model and the audit row refer to"
			);
		}

		// A file deleted inside the proposal's 24h life degrades rather than throwing —
		// and says so, which is itself something the approver wants to see. The
		// referential re-check at approval (audit #8) refuses the write regardless.
		T::equals(
			PreviewValue::forHuman((string)PHP_INT_MAX, ["type" => "image-reference"], $admin),
			"#" . PHP_INT_MAX . " (no longer available)",
			"an unresolvable reference previews as missing rather than as a number"
		);
		T::equals(
			PreviewValue::forHuman("", ["type" => "image-reference"], $admin),
			"",
			"and clearing a reference previews as empty"
		);
	}

	/**
	 * A1, for relations: the card said `["12","15"]`, while aiEntryRelationDetail and
	 * aiRelationOptions already resolve those ids to titles for the model.
	 */
	function test_relation_values_preview_as_the_rows_they_relate() {
		if (!function_exists("parity_db_available") || !parity_db_available()) {

			return;
		}

		// id > 0: a relation id list is positive integers, the same rule RelationDomain
		// applies on the way in, and bigtree_pages carries a row at id 0.
		$page = SQL::fetch("SELECT id, nav_title FROM bigtree_pages WHERE id > 0 AND nav_title != '' ORDER BY id ASC LIMIT 1");

		if (!is_array($page)) {

			return;
		}

		// Any table with a descriptor column will do; the relation seam is the same one
		// the admin's own picker reads through.
		$field = [
			"type" => "one-to-many",
			"column" => "related",
			"title" => "Related",
			"settings" => ["table" => "bigtree_pages", "title_column" => "nav_title"],
		];

		$rendered = PreviewValue::forHuman([(string)$page["id"]], $field);

		T::ok(
			strpos($rendered, (string)$page["nav_title"]) !== false,
			"a relation previews as the row's title, not as an id list"
		);
		T::ok(strpos($rendered, "#" . (int)$page["id"]) !== false, "and keeps the id");

		// The stored form is the JSON array the column holds; both sides of a diff have
		// to render the same way or the card shows a change that isn't one.
		T::equals(
			PreviewValue::forHuman(json_encode([(string)$page["id"]]), $field),
			$rendered,
			"the stored JSON form previews identically to the sifted list"
		);

		// A staging call is not the place to fire an unbounded row lookup.
		$many = PreviewValue::forHuman(range(1, PreviewValue::RELATION_CAP + 3), $field);
		T::ok(strpos($many, "+3 more") !== false, "past the cap the row says how many more there are");

		T::equals(
			PreviewValue::forHuman([], $field),
			"",
			"an empty relation previews as empty"
		);
	}

	/**
	 * A2: the tag split is emitted to be seen. Coining a tag is administrator-gated
	 * because it grows the site's shared vocabulary, and the approver is the person
	 * who would catch "Press Releases" beside the existing "Press Release" — which the
	 * card removed by hiding the key and keeping only a count in the summary.
	 */
	function test_new_tags_reach_the_card() {
		$card = ai_proposal_card_source();

		T::ok(
			strpos($card, "Array.isArray(preview.new_tags)") !== false,
			"ProposalCard renders new_tags as its own row"
		);
		T::ok(
			!in_array("new_tags", ai_card_hidden_keys(), true),
			"and no longer hides it"
		);
		T::ok(
			in_array("existing_tags", ai_card_hidden_keys(), true),
			"existing_tags stays hidden — it is `tags` minus `new_tags`, both of which are rendered"
		);

		// All three emitters, so a card that shows the names on one tool and a count on
		// another can't happen.
		foreach ([
			"add_tags" => \BigTree\Services\TagService::class,
			"create_page" => \BigTree\Services\PageService::class,
			"create_module_entry" => \BigTree\Services\AutoModuleService::class,
		] as $tool => $class) {
			T::ok(
				in_array(basename(str_replace("\\", "/", $class)) . ".php", ai_preview_emitted_keys()["new_tags"], true),
				"{$tool}'s service emits new_tags"
			);
		}
	}

	/**
	 * A4: create_page writes meta_keywords and previewed only meta_description.
	 * update_page always diffed it, because its preview is built from AI_PAGE_FIELDS —
	 * so the asymmetry was create-only, the same shape audit #14 C2 closed for the
	 * optional unsettable fields.
	 */
	function test_create_page_previews_every_field_it_writes() {
		$body = ai_surface_method_body(\BigTree\Services\PageService::class, "aiValidatePageCreate");

		T::ok(
			strpos($body, '$preview["meta_keywords"]') !== false,
			"create_page previews the meta keywords it writes"
		);
		T::ok(
			in_array("meta_keywords", \BigTree\Services\PageService::AI_PAGE_FIELDS, true),
			"and the update side still diffs the same field"
		);
	}

	/**
	 * A5: create_redirect emitted a top-level from/to pair, which ProposalCard renders
	 * as one before/after row — so the *source path* was struck through, reading as
	 * "this path is being deleted" on the one card in the set that creates something.
	 */
	function test_create_redirect_does_not_strike_through_its_source() {
		$body = ai_surface_method_body(\BigTree\Services\FourOhFourService::class, "aiValidateRedirectCreate");

		T::ok(strpos($body, '"source" => "/" . $source') !== false, "the redirect card names its source path plainly");
		T::ok(strpos($body, '"destination" => $to') !== false, "and its destination plainly");
		T::ok(
			strpos($body, '"from" => "/" . $source') === false,
			"and no longer emits the top-level from/to pair the card renders as a strikethrough"
		);

		// The value genuinely being replaced keeps its own row.
		T::ok(strpos($body, '"replaces" => $previous') !== false, "the redirect it replaces is still shown");
	}
