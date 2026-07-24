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
	 * Routes whose body is `allow_unknown` with no map (page/entry *content*) are
	 * absent by construction: their fields are the template's or the form's, and the
	 * sift seams govern them.
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
			$body = ai_inverse_route_body($route);
			T::ok(count($body) > 0, "{$route} declares a body map");

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
			$body = ai_inverse_route_body($route);
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
		foreach (glob(SERVER_ROOT . "core/inc/bigtree/api/routes/*.php") ?: [] as $file) {
			$source = (string)file_get_contents($file);
			$quoted = preg_quote($route, "/");

			// Find the route entry, then its "body" => [ … ] block.
			if (!preg_match('/"' . $quoted . '"\s*=>\s*\[/', $source, $m, PREG_OFFSET_CAPTURE)) {

				continue;
			}

			$offset = $m[0][1] + strlen($m[0][0]);

			// Bounded at the next route declaration. Unbounded, a route with no `body`
			// map silently borrowed the next route's — which reads as a body contract
			// that was checked when nothing was (audit #9 E2).
			$next = preg_match(
				'/"(?:GET|POST|PUT|PATCH|DELETE) [^"]+"\s*=>\s*\[/',
				$source,
				$next_match,
				PREG_OFFSET_CAPTURE,
				$offset
			) ? $next_match[0][1] : strlen($source);

			$block = substr($source, $offset, $next - $offset);

			if (!preg_match('/"body"\s*=>\s*\[(.*?)\]/s', $block, $body_match)) {

				return [];
			}

			preg_match_all('/"([a-zA-Z0-9_]+)"\s*=>\s*"([^"]*)"/', $body_match[1], $fields, PREG_SET_ORDER);
			$out = [];

			foreach ($fields as $field) {
				$out[$field[1]] = $field[2];
			}

			return $out;
		}

		return [];
	}
