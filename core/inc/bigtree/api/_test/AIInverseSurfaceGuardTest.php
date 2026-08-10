<?php
	/**
	 * Audit #7 Phase 3 guards: the inverse surface guard (E1) and the route-body
	 * decline-completeness leg (E7).
	 *
	 * AISurfaceGuardTest maps declared tool arguments → the read tool that returns
	 * them, so it catches a *writable* field with no read surface. It cannot catch
	 * the opposite: a backend seam that reads an argument the tool never declares, so
	 * the model can't supply it. That is exactly audit #7's C1 — update_callout's
	 * validate seam read `$args["group"]` and outOfScope() promised it, but the tool
	 * schema never declared it, so the capability was unreachable.
	 *
	 * E1 closes that direction: every `$args["…"]` a validate seam reads must be a
	 * declared property of the seam's tool (or an explicitly-listed internal key).
	 *
	 * E7 extends the route-family contract down to body fields for the pages family,
	 * where `trunk` (C2) lives: every body field POST /pages declares must be
	 * settable by a page tool or named in outOfScope().
	 */

	use BigTree\Services\AI\CapabilitySummary;

	/**
	 * Every mutating tool mapped to the validate seam it dispatches to. This is the
	 * full catalog — creates included, since a create seam reads args too.
	 *
	 * @return array<string,array{0:string,1:string}> tool => [service class, validate method]
	 */
	function ai_inverse_validate_seams(): array {

		return [
			"create_page" => [\BigTree\Services\PageService::class, "aiValidatePageCreate"],
			"update_page" => [\BigTree\Services\PageService::class, "aiValidatePageUpdate"],
			"update_page_content" => [\BigTree\Services\PageService::class, "aiValidatePageContentUpdate"],
			"archive_page" => [\BigTree\Services\PageService::class, "aiValidatePageArchive"],
			"unarchive_page" => [\BigTree\Services\PageService::class, "aiValidatePageUnarchive"],
			"move_page" => [\BigTree\Services\PageService::class, "aiValidatePageMove"],
			"save_page_revision" => [\BigTree\Services\PageService::class, "aiValidateSaveRevision"],
			"restore_page_revision" => [\BigTree\Services\PageService::class, "aiValidateRevisionRestore"],
			"create_module_entry" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryCreate"],
			"update_module_entry" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryUpdate"],
			"set_module_entry_flag" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryFlag"],
			"delete_module_entry" => [\BigTree\Services\AutoModuleService::class, "aiValidateEntryDelete"],
			"create_redirect" => [\BigTree\Services\FourOhFourService::class, "aiValidateRedirectCreate"],
			"create_module" => [\BigTree\Services\ModuleService::class, "aiValidateModuleCreate"],
			// Audit #12 C1/E3: absent since the tool landed, which is why nothing asked
			// the scaffold whether it declared the arguments it reads — or, through the
			// field-authoring contract that stands on this map, whether it gated them.
			"scaffold_module" => [\BigTree\Services\ModuleService::class, "aiValidateModuleScaffold"],
			"update_module" => [\BigTree\Services\ModuleService::class, "aiValidateModuleUpdate"],
			"create_module_group" => [\BigTree\Services\ModuleService::class, "aiValidateModuleGroupCreate"],
			"publish_pending_change" => [\BigTree\Services\PendingChangeService::class, "aiValidatePublishChange"],
			"reject_pending_change" => [\BigTree\Services\PendingChangeService::class, "aiValidateRejectChange"],
			"create_template" => [\BigTree\Services\TemplateService::class, "aiValidateTemplateCreate"],
			"update_template" => [\BigTree\Services\TemplateService::class, "aiValidateTemplateUpdate"],
			"create_callout" => [\BigTree\Services\CalloutService::class, "aiValidateCalloutCreate"],
			"update_callout" => [\BigTree\Services\CalloutService::class, "aiValidateCalloutUpdate"],
			"create_callout_group" => [\BigTree\Services\CalloutService::class, "aiValidateCalloutGroupCreate"],
			"create_user" => [\BigTree\Services\UserService::class, "aiValidateUserCreate"],
			"update_user" => [\BigTree\Services\UserService::class, "aiValidateUserUpdate"],
			"update_setting" => [\BigTree\Services\SettingService::class, "aiValidateSettingUpdate"],
			"add_tags" => [\BigTree\Services\TagService::class, "aiValidateAddTags"],
			"remove_tags" => [\BigTree\Services\TagService::class, "aiValidateRemoveTags"],
			"merge_tags" => [\BigTree\Services\TagService::class, "aiValidateTagMerge"],
			"rename_tag" => [\BigTree\Services\TagService::class, "aiValidateTagRename"],
		];
	}

	/**
	 * Keys a validate seam legitimately reads from $args that are not model-facing
	 * tool properties. Keep this short and justified — every entry is a place the
	 * model↔backend contract is deliberately looser than the schema.
	 *
	 * @return array<string,list<string>> tool => internal keys
	 */
	function ai_inverse_internal_keys(): array {

		return [
			// stageFromValidation stamps this onto the args when the tool relaxes the
			// blocked-required gate; the tools declare it, but a seam that reads it via
			// a helper can trip the scan — list it once here.
			"create_page" => ["save_as_draft"],
			"update_page" => ["save_as_draft"],
			"update_page_content" => ["save_as_draft"],
			"create_module_entry" => ["save_as_draft"],
			"update_module_entry" => ["save_as_draft"],
		];
	}

	/**
	 * E1: no validate seam reads an argument its tool doesn't declare.
	 */
	function test_every_read_argument_is_declared() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$definitions = [];

		foreach ($registry->availableTools($developer) as $tool) {
			$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];
			$definitions[$tool->name()] = is_array($properties) ? array_keys($properties) : [];
		}

		$internal = ai_inverse_internal_keys();
		$undeclared = [];

		foreach (ai_inverse_validate_seams() as $tool => [$class, $method]) {
			T::ok(isset($definitions[$tool]), "{$tool} is a registered tool with a definition");
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$method}'s source was read");

			preg_match_all('/\$args\[(?:"|\')([a-zA-Z0-9_]+)(?:"|\')\]/', $body, $matches);
			$read = array_values(array_unique($matches[1]));
			$allowed = array_merge($definitions[$tool] ?? [], $internal[$tool] ?? []);

			foreach ($read as $key) {
				if (!in_array($key, $allowed, true)) {
					$undeclared[] = "{$tool}.{$key} (read in {$method})";
				}
			}
		}

		T::equals(
			implode(", ", $undeclared),
			"",
			"every argument a validate seam reads is declared by its tool (or listed as internal)"
		);
	}

	/**
	 * Audit #9 E2: the body-field contract, for every write route the assistant is
	 * meant to cover — not just `pages`.
	 *
	 * Audit #7 shipped this scoped to POST /pages and recorded the general version as
	 * future work. Audit #9's B1–B3 were all instances of what the general version
	 * would have caught: a field the route accepts, no tool sets, and no decline
	 * names — so the model discovers it by failing.
	 *
	 * Each field of each route must be exactly one of:
	 *
	 *   - settable — declared by one of the route's named tools;
	 *   - exempt — with the reason written down (a field the tools express
	 *     differently, or one the server derives);
	 *   - declined — with a phrase that must actually appear in outOfScope().
	 *
	 * Routes whose body is `allow_unknown` and whose service reads no field by name
	 * (page/entry *content*) are absent by construction: their fields are the
	 * template's or the form's, and the sift seams govern them.
	 *
	 * Audit #14 E2: the update side is here now. Every PATCH route in the covered
	 * families except `PATCH /users/{id:int}` declares `allow_unknown => true` with no
	 * `body` map, and the classification leg below skipped any endpoint whose declared
	 * body was empty — so for thirteen audits this contract classified the **create**
	 * side only. That was invisible from either direction: the map looked complete, and
	 * every route it named really was classified. The update side happened to be at
	 * parity by hand, but audit #7's C1 (`update_callout.group`) and audit #9's B2
	 * (`update_module.class`) were both exactly this class of finding, both found by
	 * reading rather than by a guard. A PATCH route's effective body is what its service
	 * actually reads — see ai_inverse_service_body.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function ai_inverse_body_contracts(): array {

		return [
			"POST /pages" => [
				"tools" => ["create_page", "update_page", "update_page_content"],
				"exempt" => [
					// The tools take page content as `content`, keyed by template
					// resource id, and sift it against the template's own schema.
					"resources" => "the tools express page content as `content`, sifted against the template schema",
					"open_graph" => "expressed as the og_title / og_description arguments rather than an object",
					"publish" => "the tools invert it as save_as_draft; publishing is decided by rank at approval",
				],
				"declined" => [
					"trunk" => "site trunk",
				],
			],
			// The update side (audit #14 E2). Each of these declares no `body` map, so
			// the fields are the ones its service reads by name — derived, not copied,
			// which is the difference between a contract and a comment.
			"PATCH /pages/{id:int}" => [
				"tools" => ["update_page", "update_page_content", "add_tags", "remove_tags"],
				"exempt" => [
					"resources" => "the tools express page content as `content`, sifted against the template schema",
					"open_graph" => "expressed as the og_title / og_description arguments rather than an object",
					"publish" => "the tools invert it as save_as_draft; publishing is decided by rank at approval",
				],
				"declined" => [
					"trunk" => "site trunk",
				],
			],
			// Editing a queued draft. update_page resolves a "p{change}" reference onto
			// the draft and amends it in place, which is how an editor's edit collapses
			// onto their own pending change rather than making a second one.
			"PATCH /pages/pending/{pcid:int}" => [
				"tools" => ["update_page", "update_page_content"],
				"exempt" => [
					"open_graph" => "expressed as the og_title / og_description arguments rather than an object",
					"publish" => "the tools invert it as save_as_draft; publishing a draft is publish_pending_change",
					// Deliberate and recorded rather than declined: create_page stages a
					// draft's tags with the draft, and add_tags/remove_tags take a live
					// page id. Amending only the tags of a page that does not exist yet is
					// not a capability the catalog offers.
					"tags" => "a draft's tags are staged with it by create_page; add_tags/remove_tags target a live "
						. "page id, and an unapproved draft has none",
				],
			],
			"PATCH /templates/{id}" => [
				"tools" => ["create_template", "update_template"],
				"exempt" => [
					"resources" => "the tools express a template's fields as `fields`",
				],
				"declined" => [
					"module" => "binding a template to a module",
					"hooks" => "configuring its publish hooks",
				],
			],
			"PATCH /callouts/{id}" => [
				"tools" => ["create_callout", "update_callout"],
				"exempt" => [
					"resources" => "the tools express a callout's fields as `fields`",
				],
			],
			"PATCH /modules/{id}" => [
				"tools" => ["create_module", "update_module"],
				"declined" => [
					"gbp" => "group-based permissions",
				],
			],
			// update_setting writes the value; every other key on this route is the
			// setting's *definition*, which retypes or re-encrypts every stored value
			// with no migration (audit #9 B2/D3).
			"PATCH /settings/{id}" => [
				"tools" => ["update_setting"],
				"declined" => [
					"name" => "creating, deleting or redefining settings",
					"description" => "creating, deleting or redefining settings",
					"type" => "creating, deleting or redefining settings",
					"settings" => "creating, deleting or redefining settings",
					"locked" => "creating, deleting or redefining settings",
					"system" => "creating, deleting or redefining settings",
					"extension" => "creating, deleting or redefining settings",
					// `id` is deliberately absent: update_setting declares an `id`
					// argument, so by name it is settable, and the guard would (rightly)
					// call an exemption for it stale. The two mean different things —
					// the tool's `id` names the setting to write, the route's renames it
					// — and the rename is declined by the same "redefining settings"
					// line as the rest of the definition, which says "even the setting's
					// own id" in as many words.
					"encrypted" => "reading or writing encrypted settings",
				],
			],
			"POST /users" => [
				"tools" => ["create_user", "update_user"],
				"declined" => [
					"level" => "levels, permissions or passwords",
					"password" => "levels, permissions or passwords",
					"permissions" => "levels, permissions or passwords",
				],
			],
			"PATCH /users/{id:int}" => [
				"tools" => ["create_user", "update_user"],
				"declined" => [
					"level" => "levels, permissions or passwords",
					"permissions" => "levels, permissions or passwords",
				],
			],
			// Wholly declined: update_setting writes a setting's *value*, and the
			// definition — name, type, options, locked, encrypted, even the id — is a
			// schema change over stored values with no migration (audit #9 B2/D3).
			"POST /settings" => [
				"tools" => [],
				"declined_all" => "creating, deleting or redefining settings",
			],
			"POST /callouts" => [
				"tools" => ["create_callout", "update_callout"],
				"exempt" => [
					"resources" => "the tools express a callout's fields as `fields`",
				],
			],
			"POST /callout-groups" => [
				"tools" => ["create_callout_group"],
				"exempt" => [
					"id" => "assigned by BigTreeJSONDB::insert; a caller-chosen group id is never accepted",
				],
			],
			"POST /modules" => [
				"tools" => ["create_module", "update_module"],
				"declined" => [
					"table" => "tables, forms, views or actions",
					"gbp" => "group-based permissions",
				],
			],
			// Audit #12 C2. A full body map with no entry here, so `class` and `gbp`
			// were unclassified and the capabilities A4/A5 found missing were never put
			// to anyone as a decision. Audit #9's E2 leg made this map mandatory for
			// action routes with bodies; a whole new route family with a body still
			// slipped in, which is what the leg below now closes.
			"POST /modules/scaffold" => [
				"tools" => ["scaffold_module"],
				"exempt" => [
					"class" => "the scaffold deliberately writes no class; the Module Designer owns creating one, "
						. "and create_module refuses to wire a module to one that doesn't exist",
				],
				"declined" => [
					"gbp" => "group-based permissions",
				],
			],
			"POST /module-groups" => [
				"tools" => ["create_module_group"],
				"exempt" => [
					"route" => "derived from the group's name; a module group's route is not a thing the assistant picks",
				],
			],
			"POST /templates" => [
				"tools" => ["create_template", "update_template"],
				"exempt" => [
					"resources" => "the tools express a template's fields as `fields`",
				],
				"declined" => [
					"module" => "binding a template to a module",
					"hooks" => "configuring its publish hooks",
				],
			],
			"POST /404s" => [
				"tools" => ["create_redirect"],
			],
			// Setting a redirect on a logged 404 rather than coining one: same act, and
			// create_redirect expresses the destination as `to`. Surfaced by the E6 leg
			// below (audit #12 C2) — a body map with no entry, on a route the family
			// contract already called covered.
			"POST /404s/{id:int}/redirect" => [
				"tools" => ["create_redirect"],
				"exempt" => [
					"url" => "create_redirect names the destination `to`, and the 404 being redirected is named by "
						. "`from` rather than by row id",
				],
			],
			// The semantic search endpoint behind semantic_search, which takes the same
			// two things under the names every read tool uses.
			"POST /search/ai" => [
				"tools" => ["semantic_search"],
				"exempt" => [
					"q" => "the tool names it `query`, as every other search tool does",
					"limit" => "the read tools' shared window argument, applied from the tool context rather than "
						. "declared per tool",
				],
			],
			"POST /tags" => [
				"tools" => ["add_tags"],
				"exempt" => [
					"tag" => "add_tags coins tags as a list under `tags` while tagging something, never as a bare record",
				],
			],
			// The sub-resource writes. Audit #9 listed the top-level families by name
			// and these fell outside the list, which is the same optionality E1 closed
			// on the verb map: an action route with a body is a body contract, and a
			// field it gains should have to be classified like any other.
			"POST /pages/{id:int}/move" => [
				"tools" => ["move_page"],
			],
			"POST /pages/{id:int}/revisions" => [
				"tools" => ["save_page_revision"],
			],
			"POST /tags/merge" => [
				"tools" => ["merge_tags"],
			],
		];
	}

	function test_write_route_body_fields_are_settable_exempt_or_declined() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$declines = strtolower(implode(" | ", array_keys(CapabilitySummary::outOfScope())));
		$unaccounted = [];

		foreach (ai_inverse_body_contracts() as $route => $contract) {
			$body = ai_inverse_effective_body($route);
			T::ok(count($body) > 0, "{$route} accepts a body this contract can enumerate");

			$settable = [];

			foreach ($contract["tools"] as $name) {
				$tool = $registry->get($name);
				T::ok($tool !== null, "{$route}'s contract names the registered tool {$name}");

				if ($tool === null) {

					continue;
				}

				$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];

				foreach (is_array($properties) ? array_keys($properties) : [] as $arg) {
					$settable[$arg] = true;
				}
			}

			$exempt = $contract["exempt"] ?? [];
			$declined = $contract["declined"] ?? [];
			$declined_all = (string)($contract["declined_all"] ?? "");

			if ($declined_all !== "") {
				T::ok(
					strpos($declines, $declined_all) !== false,
					"{$route} is declined wholesale by wording matching \"{$declined_all}\""
				);

				continue;
			}

			foreach (array_keys($body) as $field) {
				if (isset($settable[$field]) || isset($exempt[$field])) {

					continue;
				}

				if (isset($declined[$field])) {
					T::ok(
						strpos($declines, strtolower($declined[$field])) !== false,
						"{$route}.{$field} is declined by wording matching \"{$declined[$field]}\""
					);

					continue;
				}

				$unaccounted[] = "{$route}.{$field}";
			}
		}

		T::equals(
			implode(", ", $unaccounted),
			"",
			"every write route's body fields are settable by a tool, exempt with a reason, or declined by name"
		);
	}

	/**
	 * Audit #12 C2/E6: the body contract is not optional.
	 *
	 * Every route classified COVERED by the route-family contract that declares a
	 * `body` map must have an entry above. `POST /modules/scaffold` had a twelve-field
	 * body and no entry for a whole audit cycle — so the fields it accepts were never
	 * put to anyone as settable, exempt or declined, which is the decision this map
	 * exists to force. Being in one contract and absent from the other is precisely
	 * the state that reads as "checked".
	 */
	function test_every_covered_route_with_a_body_map_is_classified() {
		$contracts = ai_inverse_body_contracts();
		$missing = [];

		foreach (ai_contract_covered() as $family => $coverage) {
			foreach (ai_contract_route_families()[$family] ?? [] as $endpoint) {
				$verb = strtok($endpoint, " ");

				if (!in_array($verb, ai_contract_write_verbs(), true)) {

					continue;
				}

				// A per-verb map can decline the very verb whose body would need
				// classifying; a declined endpoint has no capability to describe.
				$named = is_array($coverage) ? (string)($coverage[$verb] ?? "") : (string)$coverage;

				if ($named === "" || strpos($named, "declined:") === 0) {

					continue;
				}

				if (isset($contracts[$endpoint]) || ai_inverse_effective_body($endpoint) === []) {

					continue;
				}

				$missing[] = $endpoint;
			}
		}

		T::equals(
			implode(", ", array_unique($missing)),
			"",
			"every covered write route that declares a body map is classified field by field"
		);
	}

	/**
	 * The map must not outlive the fields it classifies — in either direction. A
	 * field the route dropped is an entry describing nothing; a field a tool has
	 * since gained is an exemption (or a decline) recording a decision that has been
	 * reversed, which is how `create_callout_group.callouts` read for exactly as long
	 * as it took to add the argument.
	 */
	function test_body_contract_has_no_stale_entries() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$stale = [];
		$now_settable = [];

		foreach (ai_inverse_body_contracts() as $route => $contract) {
			$body = ai_inverse_effective_body($route);
			$classified = array_merge($contract["exempt"] ?? [], $contract["declined"] ?? []);
			$settable = [];

			foreach ($contract["tools"] as $name) {
				$tool = $registry->get($name);
				$properties = $tool !== null
					? ($tool->definition($developer)["function"]["parameters"]["properties"] ?? [])
					: [];

				foreach (is_array($properties) ? array_keys($properties) : [] as $arg) {
					$settable[$arg] = true;
				}
			}

			foreach (array_keys($classified) as $field) {
				if (!isset($body[$field])) {
					$stale[] = "{$route}.{$field}";

					continue;
				}

				if (isset($settable[$field])) {
					$now_settable[] = "{$route}.{$field}";
				}
			}
		}

		T::equals(implode(", ", $stale), "", "no body-contract entry names a field its route no longer declares");
		T::equals(
			implode(", ", $now_settable),
			"",
			"no field is classified exempt or declined while a tool actually sets it"
		);
	}

	/**
	 * The audit #7 C2 guarantee the pages-scoped version carried, kept explicitly:
	 * `trunk` is a real POST /pages body field and it is a named decline.
	 */
	function test_page_trunk_is_a_real_body_field_and_declined() {
		$body = ai_inverse_route_body("POST /pages");
		$declines = implode(" ", CapabilitySummary::outOfScopeLines());

		T::ok(isset($body["trunk"]), "trunk is a POST /pages body field");
		T::ok(stripos($declines, "trunk") !== false, "and trunk is named in an out-of-scope decline line");
	}

	/**
	 * The body field => rule map a route declares, parsed from the route files.
	 *
	 * @return array<string,string>
	 */
	function ai_inverse_route_body(string $route): array {
		$block = ai_inverse_route_block($route);

		if ($block === "" || !preg_match('/"body"\s*=>\s*\[(.*?)\]/s', $block, $body_match)) {

			return [];
		}

		preg_match_all('/"([a-zA-Z0-9_]+)"\s*=>\s*"([^"]*)"/', $body_match[1], $fields, PREG_SET_ORDER);
		$out = [];

		foreach ($fields as $field) {
			$out[$field[1]] = $field[2];
		}

		return $out;
	}

	/**
	 * A route's own declaration block, bounded at the next route declaration.
	 *
	 * Unbounded, a route with no `body` map silently borrowed the next route's — which
	 * reads as a body contract that was checked when nothing was (audit #9 E2).
	 */
	function ai_inverse_route_block(string $route): string {
		foreach (glob(SERVER_ROOT . "core/inc/bigtree/api/routes/*.php") ?: [] as $file) {
			$source = (string)file_get_contents($file);
			$quoted = preg_quote($route, "/");

			if (!preg_match('/"' . $quoted . '"\s*=>\s*\[/', $source, $m, PREG_OFFSET_CAPTURE)) {

				continue;
			}

			$offset = $m[0][1] + strlen($m[0][0]);
			$next = preg_match(
				'/"(?:GET|POST|PUT|PATCH|DELETE) [^"]+"\s*=>\s*\[/',
				$source,
				$next_match,
				PREG_OFFSET_CAPTURE,
				$offset
			) ? $next_match[0][1] : strlen($source);

			return substr($source, $offset, $next - $offset);
		}

		return "";
	}

	/**
	 * The service a route dispatches to, with its short class name resolved through the
	 * route file's own `use` statements.
	 *
	 * @return array{0:string,1:string}|null
	 */
	function ai_inverse_route_service(string $route): ?array {
		foreach (glob(SERVER_ROOT . "core/inc/bigtree/api/routes/*.php") ?: [] as $file) {
			$source = (string)file_get_contents($file);

			if (!preg_match('/"' . preg_quote($route, "/") . '"\s*=>\s*\[/', $source)) {

				continue;
			}

			$block = ai_inverse_route_block($route);

			if (!preg_match('/"service"\s*=>\s*\[([A-Za-z0-9_\\\\]+)::class\s*,\s*"([A-Za-z0-9_]+)"\]/', $block, $m)) {

				return null;
			}

			$class = $m[1];

			if (strpos($class, "\\") === false
				&& preg_match('/use\s+([A-Za-z0-9_\\\\]+\\\\' . preg_quote($class, "/") . ');/', $source, $use)) {
				$class = $use[1];
			}

			return [$class, $m[2]];
		}

		return null;
	}

	/**
	 * The body fields a route's service actually reads by name — the *effective* body of
	 * a route that declares no `body` map (audit #14 E2).
	 *
	 * Derived rather than hand-copied, for the reason IconDomain gives about vocabulary
	 * lists: a copy is one more thing to drift, and the whole point of a contract is
	 * that it can't silently stop describing the code. Three shapes are recognized,
	 * which between them cover every update service in the tree:
	 *
	 *   - direct reads of the body variable (`$d["nav_title"]`, however guarded);
	 *   - `FieldSpec::update($d, self::FIELDS)`, where the field set is a class constant
	 *     read back through reflection;
	 *   - one or two levels of `$this->method(…, $d, …)` delegation, matched by argument
	 *     position onto the callee's parameter name (`PageService::update` reads almost
	 *     nothing itself and hands the body to `performUpdate`).
	 *
	 * This is a lower bound, not a parse: a service that stores the whole body wholesale
	 * (`PageService::updatePending` writes it into the change JSON) yields only the keys
	 * it names. That is enough for the guard's job, which is to force a decision on every
	 * field that is visible and to fail the moment the visible set grows.
	 *
	 * @return array<string,string> field => "" (shaped like ai_inverse_route_body's map)
	 */
	function ai_inverse_service_body(string $route): array {
		$service = ai_inverse_route_service($route);

		if ($service === null) {

			return [];
		}

		$seen = [];
		$keys = ai_inverse_body_keys_in($service[0], $service[1], null, 0, $seen);
		$out = [];

		foreach ($keys as $key) {
			$out[$key] = "";
		}

		return $out;
	}

	/**
	 * @param array<string,bool> $seen
	 * @return list<string>
	 */
	function ai_inverse_body_keys_in(string $class, string $method, ?string $var, int $depth, array &$seen): array {
		if ($depth > 2 || !method_exists($class, $method)) {

			return [];
		}

		$signature = "{$class}::{$method}:" . (string)$var;

		if (isset($seen[$signature])) {

			return [];
		}

		$seen[$signature] = true;
		$body = ai_surface_method_body($class, $method);

		if ($body === "") {

			return [];
		}

		if ($var === null) {
			if (!preg_match('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*\$request->body\s*;/', $body, $m)) {

				return [];
			}

			$var = $m[1];
		}

		$quoted = preg_quote($var, "/");
		$keys = [];

		if (preg_match_all('/\$' . $quoted . '\["([a-zA-Z0-9_]+)"\]/', $body, $m)) {
			foreach ($m[1] as $key) {
				$keys[$key] = true;
			}
		}

		if (preg_match_all(
			'/FieldSpec::(?:create|update)\(\s*\$' . $quoted . '\s*,\s*self::([A-Z_]+)\s*\)/',
			$body,
			$m
		)) {
			foreach ($m[1] as $constant) {
				$spec = (new ReflectionClass($class))->getConstant($constant);

				foreach (is_array($spec) ? array_keys($spec) : [] as $key) {
					$keys[(string)$key] = true;
				}
			}
		}

		// Follow the body into a helper it is handed to, matched by argument position.
		if (preg_match_all('/\$this->([a-zA-Z0-9_]+)\(([^;()]*(?:\([^()]*\)[^;()]*)*)\)/', $body, $calls, PREG_SET_ORDER)) {
			foreach ($calls as $call) {
				$arguments = array_map("trim", explode(",", $call[2]));
				$position = array_search("\$" . $var, $arguments, true);

				if ($position === false || !method_exists($class, $call[1])) {

					continue;
				}

				$parameters = (new ReflectionMethod($class, $call[1]))->getParameters();

				if (!isset($parameters[$position])) {

					continue;
				}

				foreach (
					ai_inverse_body_keys_in($class, $call[1], $parameters[$position]->getName(), $depth + 1, $seen)
					as $key
				) {
					$keys[$key] = true;
				}
			}
		}

		return array_keys($keys);
	}

	/**
	 * Everything a route accepts: the fields it declares, plus the fields its service
	 * reads when it declares none. One notion of "this route's body", so a PATCH is held
	 * to the same standard as the POST beside it.
	 *
	 * @return array<string,string>
	 */
	function ai_inverse_effective_body(string $route): array {
		$declared = ai_inverse_route_body($route);

		return $declared !== [] ? $declared : ai_inverse_service_body($route);
	}

	/**
	 * Audit #13 guard E3: the extension-ownership contract.
	 *
	 * `ExtensionService` files an installed extension's records into the very same
	 * JSON-DB stores the site's own live in, keyed `{ext}*{local}` with an
	 * `extension` key — and nothing on the AI surface knew that key existed. Two
	 * consequences, both invisible from any existing guard because both are about
	 * what the record *is* rather than what was written into it:
	 *
	 *  (a) an upgrade re-imports the extension's manifest over these records
	 *      (`BigTreeAdmin::installExtension` deletes and re-inserts every one of
	 *      them), so an approved field addition is reverted at the next upgrade with
	 *      no warning anywhere;
	 *  (b) since audit #10 C1 the field diff names the render file to edit, and
	 *      `TemplateScaffold::safeId()` strips the `*` — so
	 *      `com.example.blog*sidebar` was reported as
	 *      `templates/basic/comexampleblogsidebar.php` while the router loads
	 *      `extensions/com.example.blog/templates/basic/sidebar.php`. The path on the
	 *      card could never be the file that renders.
	 *
	 * The enumeration runs from the namespacing code itself, so a store that starts
	 * carrying an `extension` key fails here rather than shipping unclassified.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function ai_extension_namespaced_stores(): array {

		return [
			"templates" => [
				"reads" => [
					[\BigTree\Services\TemplateService::class, "present"],
					[\BigTree\Services\TemplateService::class, "aiListTemplates"],
				],
				"update_seam" => [\BigTree\Services\TemplateService::class, "aiValidateTemplateUpdate"],
			],
			"callouts" => [
				"reads" => [
					[\BigTree\Services\CalloutService::class, "present"],
					[\BigTree\Services\CalloutService::class, "aiGetCallout"],
				],
				"update_seam" => [\BigTree\Services\CalloutService::class, "aiValidateCalloutUpdate"],
			],
			"modules" => [
				"reads" => [
					[\BigTree\Services\ModuleService::class, "present"],
					[\BigTree\Services\ModuleService::class, "aiGetModule"],
				],
				"update_seam" => [\BigTree\Services\ModuleService::class, "aiValidateModuleUpdate"],
			],
			// The one store installExtension deliberately does *not* drop on upgrade:
			// "we don't drop settings because they have user data" (admin.php). A
			// setting's value is the user's, survives the re-import, and is the only
			// thing update_setting writes — the definition is declined outright.
			"settings" => [
				"exempt" => "an upgrade re-imports every other component but leaves settings alone precisely "
					. "because they hold user data, and update_setting writes only the value",
			],
			"field-types" => [
				"exempt" => "no tool reads or writes a field-type record; the catalogue lists ids, and an "
					. "extension-owned id carries its `{ext}*` prefix in plain sight",
			],
			"feeds" => [
				"exempt" => "feeds have no AI surface at all — no read tool, no write tool, no decline line "
					. "needed because nothing reaches them",
			],
			"module-groups" => [
				"exempt" => "create_module_group coins a new group and there is no update tool, so no edit "
					. "can be reverted by an upgrade",
			],
		];
	}

	/**
	 * The JSON-DB stores the extension machinery stamps an `extension` key onto,
	 * read from the code that does the stamping.
	 *
	 * @return list<string>
	 */
	function ai_extension_namespaced_store_names(): array {
		$found = [];

		foreach (["core/inc/bigtree/services/ExtensionService.php", "core/inc/bigtree/admin.php"] as $relative) {
			$source = (string)file_get_contents(SERVER_ROOT . $relative);
			preg_match_all('/BigTreeJSONDB::(?:insert|update)\("([a-z-]+)"/', $source, $matches, PREG_OFFSET_CAPTURE);

			foreach ($matches[1] as $hit) {
				// The `extension` key is set in the same call's argument list; a window
				// rather than a balanced parse, because the calls are one to five lines.
				if (strpos(substr($source, (int)$hit[1], 600), '"extension" =>') !== false) {
					$found[] = (string)$hit[0];
				}
			}
		}

		return array_values(array_unique($found));
	}

	/**
	 * E3a: every namespaced store is classified, and the classification doesn't
	 * outlive the store.
	 */
	function test_every_extension_namespaced_store_is_classified() {
		$stores = ai_extension_namespaced_store_names();
		$contract = ai_extension_namespaced_stores();

		T::ok(count($stores) >= 6, "the namespaced stores were found (" . implode(", ", $stores) . ")");

		$unclassified = array_values(array_diff($stores, array_keys($contract)));
		T::equals(
			implode(", ", $unclassified),
			"",
			"every store the extension machinery namespaces is classified as surfaced or exempt"
		);

		$stale = array_values(array_diff(array_keys($contract), $stores));
		T::equals(implode(", ", $stale), "", "no classification names a store nothing namespaces");
	}

	/**
	 * E3b: a store whose records the assistant can read and edit says who owns them
	 * — on every read payload, and at the seam that stages the edit.
	 */
	function test_extension_owned_records_are_surfaced_and_warned_about() {
		$missing = [];

		foreach (ai_extension_namespaced_stores() as $store => $entry) {
			if (isset($entry["exempt"])) {
				T::ok(trim((string)$entry["exempt"]) !== "", "{$store} states why it needs no surface");

				continue;
			}

			foreach ((array)($entry["reads"] ?? []) as [$class, $method]) {
				$body = ai_surface_method_body($class, $method);

				if (strpos($body, '"extension"') === false) {
					$missing[] = "{$store}: {$class}::{$method} omits the extension key";
				}
			}

			[$class, $method] = $entry["update_seam"];
			$body = ai_surface_method_body($class, $method);

			if (strpos($body, "ExtensionDomain::warning") === false) {
				$missing[] = "{$store}: {$method} stages an edit without warning that an upgrade reverts it";
			}
		}

		T::equals(
			implode("; ", $missing),
			"",
			"every extension-owned record is surfaced on the read side and warned about on the write side"
		);
	}

	/**
	 * E3c: the render-file path an update proposal names is the file the router
	 * actually loads. `safeId()` stripped the `*` out of `{ext}*{local}`, so the card
	 * named a file that has never existed and could not be created usefully — a
	 * developer following it writes a dead file.
	 */
	function test_an_extension_namespaced_id_resolves_under_the_extensions_directory() {
		$scaffold = \BigTree\Api\TemplateScaffold::class;

		T::equals(
			$scaffold::templatePath("com.example.blog*sidebar", false),
			"extensions/com.example.blog/templates/basic/sidebar.php",
			"a basic extension template points at the file router.php includes"
		);
		T::equals(
			$scaffold::templatePath("com.example.blog*listing", true),
			"extensions/com.example.blog/templates/routed/listing/default.php",
			"and a routed one at the directory BigTree::route() walks"
		);
		T::equals(
			$scaffold::calloutPath("com.example.blog*promo"),
			"extensions/com.example.blog/templates/callouts/promo.php",
			"a callout follows the same layout the packager built"
		);

		// The site's own records are untouched, and neither half of a namespaced id
		// can climb out of the directory it names.
		T::equals($scaffold::templatePath("article", false), "templates/basic/article.php", "a site template is unchanged");
		T::equals($scaffold::calloutPath("promo"), "templates/callouts/promo.php", "and so is a site callout");
		T::equals(
			$scaffold::templatePath("../../etc*../../passwd", false),
			"extensions/etc/templates/basic/passwd.php",
			"both halves of a namespaced id are sanitized, not just the local one"
		);
	}

	// — audit #20 guard E4: a settings-bearing update argument applies, or says it doesn't —

	/**
	 * The per-field arguments an update surface's field shape carries that
	 * `Resources::mergeAiFields` *does* copy onto a field carried over by id at an
	 * unchanged type. Everything else in the field shape is dropped for such a field,
	 * so it has to say so.
	 */
	function ai_merge_carried_field_args(): array {

		return ["id", "type", "title", "subtitle"];
	}

	/**
	 * Arguments deliberately dropped without saying so, with the reason. Empty, and
	 * meant to stay that way: `options` was the entry this would have held, which is
	 * the point of the guard.
	 *
	 * @return array<string,array<string,string>> tool => [argument => reason]
	 */
	function ai_merge_dropped_arg_exemptions(): array {

		return [];
	}

	/** The update tools whose `fields` argument goes through mergeAiFields. */
	function ai_merge_update_surfaces(): array {

		return ["update_template", "update_callout"];
	}

	/**
	 * E4: every per-field property an update tool declares either survives the merge
	 * or is documented as applying to newly added fields only.
	 *
	 * `mergeAiFields` takes a retained field's stored row wholesale — which is right,
	 * and is what stops an `update_template` from flattening the image presets and
	 * matrix subfields the assistant's field shape can't restate. What was wrong is
	 * that `required` and `default` disclosed it in their own descriptions and
	 * `options` did not: "add Enormous to the Size dropdown" is an ordinary request,
	 * the model sends `options`, the merge drops it, the card's field diff shows
	 * nothing, and the model reports the change as made (audit #20 A4).
	 */
	function test_update_field_arguments_apply_or_disclose_that_they_do_not() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$carried = ai_merge_carried_field_args();
		$exempt = ai_merge_dropped_arg_exemptions();
		$undisclosed = [];
		$checked = 0;

		foreach ($registry->availableTools($developer) as $tool) {
			if (!in_array($tool->name(), ai_merge_update_surfaces(), true)) {

				continue;
			}

			$properties = $tool->definition($developer)["function"]["parameters"]["properties"]["fields"]["items"]["properties"] ?? [];
			T::ok(is_array($properties) && $properties, "{$tool->name()} declares per-field properties");

			foreach ((array)$properties as $arg => $descriptor) {
				if (in_array((string)$arg, $carried, true) || isset($exempt[$tool->name()][$arg])) {

					continue;
				}

				$checked++;
				$description = (string)($descriptor["description"] ?? "");

				if (stripos($description, "newly added fields only") === false) {
					$undisclosed[] = "{$tool->name()}.{$arg}";
				}
			}
		}

		T::ok($checked > 0, "the update surfaces declare arguments the merge drops, so this checked something");
		T::equals(
			implode(", ", $undisclosed),
			"",
			"every dropped per-field argument says \"newly added fields only\" in its own description"
		);
	}

	/** No exemption may outlive the argument it excuses. */
	function test_no_merge_dropped_arg_exemption_is_stale() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$declared = [];

		foreach ($registry->availableTools($developer) as $tool) {
			if (!in_array($tool->name(), ai_merge_update_surfaces(), true)) {

				continue;
			}

			$properties = $tool->definition($developer)["function"]["parameters"]["properties"]["fields"]["items"]["properties"] ?? [];
			$declared[$tool->name()] = is_array($properties) ? array_keys($properties) : [];
		}

		$stale = [];

		foreach (ai_merge_dropped_arg_exemptions() as $tool => $args) {
			foreach ($args as $arg => $reason) {
				T::ok($reason !== "", "the {$tool}.{$arg} exemption states why the silence is correct");

				if (!in_array((string)$arg, $declared[$tool] ?? [], true)) {
					$stale[] = "{$tool}.{$arg}";
				}
			}
		}

		T::equals(implode(", ", $stale), "", "no exemption names an argument that is no longer declared");
	}

	/**
	 * The runtime half of the same disclosure. A schema description is advice the
	 * model may not follow, so a supplied-and-dropped argument comes back as a
	 * recoverable error naming the argument and the field it was sent for, rather
	 * than staging a card that under-delivers.
	 */
	function test_a_dropped_field_argument_comes_back_as_a_recoverable_error() {
		$existing = [[
			"id" => "size",
			"type" => "list",
			"title" => "Size",
			"subtitle" => "",
			"settings" => [
				"list_type" => "static",
				"list" => [["value" => "Small", "description" => "Small"], ["value" => "Large", "description" => "Large"]],
			],
		]];

		$error = (string)\BigTree\Api\Resources::aiIgnoredFieldSettingsError(
			[["id" => "size", "options" => ["Small", "Medium", "Large"]]],
			$existing,
			"template"
		);

		T::ok($error !== "", "restating an existing field's options is refused rather than silently dropped");
		T::ok(strpos($error, "options") !== false, "the error names the argument that was ignored");
		T::ok(strpos($error, "Size") !== false, "and the field it was sent for");
		T::ok(
			strpos($error, "Developer → Templates") !== false,
			"and where a developer actually makes the change"
		);

		// …and it is quiet when the argument would change nothing, so the natural
		// "here is the complete field list" call stays usable.
		T::equals(
			\BigTree\Api\Resources::aiIgnoredFieldSettingsError(
				[["id" => "size", "title" => "Garment Size", "options" => ["Small", "Large"]]],
				$existing,
				"template"
			),
			null,
			"restating the options a field already has drops nothing, so it says nothing"
		);

		// A newly added field is built from the proposal, settings and all — nothing
		// is dropped there and nothing is reported.
		T::equals(
			\BigTree\Api\Resources::aiIgnoredFieldSettingsError(
				[["id" => "colour", "type" => "list", "options" => ["Red"], "default" => "Red", "required" => true]],
				$existing,
				"template"
			),
			null,
			"a new field's settings all apply, so none are reported"
		);

		// …as is a retyped one, whose settings are deliberately discarded and whose
		// retype the card's field diff discloses.
		T::equals(
			\BigTree\Api\Resources::aiIgnoredFieldSettingsError(
				[["id" => "size", "type" => "text", "default" => "Medium"]],
				$existing,
				"template"
			),
			null,
			"a retyped field takes the proposal's settings, so none are reported"
		);

		// The other two arguments the merge drops, on the same field.
		foreach (["required" => true, "default" => "Large"] as $arg => $value) {
			$dropped = (string)\BigTree\Api\Resources::aiIgnoredFieldSettingsError(
				[["id" => "size", $arg => $value]],
				$existing,
				"template"
			);

			T::ok(strpos($dropped, $arg) !== false, "a dropped \"{$arg}\" is reported too");
		}
	}

	/**
	 * The corroborating half of A4: the merge's carry-over branch is what these
	 * arguments are dropped by, so the branch has to still be the shape the guard
	 * above assumes.
	 */
	function test_the_merge_carries_over_only_the_declared_field_arguments() {
		$existing = [[
			"id" => "size",
			"type" => "list",
			"title" => "Size",
			"subtitle" => "",
			"settings" => [
				"list_type" => "static",
				"list" => [["value" => "Small", "description" => "Small"]],
				"validation" => "required",
			],
		]];

		$merged = \BigTree\Api\Resources::mergeAiFields(
			[["id" => "size", "title" => "Garment Size", "options" => ["Small", "Medium"], "required" => false, "default" => "Medium"]],
			$existing,
			"template"
		);

		T::equals((string)($merged[0]["title"] ?? ""), "Garment Size", "a restated title applies");
		T::equals(
			count($merged[0]["settings"]["list"] ?? []),
			1,
			"…and the stored options survive, which is what the preservation is for"
		);
		T::equals(
			(string)($merged[0]["settings"]["validation"] ?? ""),
			"required",
			"…as do the validation rules the page and entry gates depend on"
		);
		T::ok(!isset($merged[0]["settings"]["default"]), "…and no default is written in");
	}
