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
	 * E7: every body field POST /pages declares is either settable by a page tool or
	 * named in outOfScope(). Anchored on the pages family because that is where the
	 * `trunk` decline (C2) has to hold.
	 */
	function test_pages_body_fields_are_settable_or_declined() {
		$body = ai_inverse_route_body("POST /pages");
		T::ok(count($body) > 0, "POST /pages declares a body");

		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$settable = [];

		foreach (["create_page", "update_page", "update_page_content"] as $name) {
			$tool = $registry->get($name);

			if ($tool === null) {

				continue;
			}

			$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];

			foreach (is_array($properties) ? array_keys($properties) : [] as $arg) {
				$settable[$arg] = true;
			}
		}

		// Body fields the assistant deliberately doesn't set and that aren't a named
		// decline either — routing/relationship plumbing the tools own differently.
		$exempt = [
			"parent" => true, "position" => true, "resources" => true, "tags" => true,
			"publish_now" => true, "return_id" => true,
			// The assistant expresses Open Graph through the og_title/og_description
			// args rather than a whole open_graph object.
			"open_graph" => true,
		];

		$declines = implode(" ", CapabilitySummary::outOfScopeLines());
		$unaccounted = [];

		foreach (array_keys($body) as $field) {
			if (isset($settable[$field]) || isset($exempt[$field])) {

				continue;
			}

			// A field named in a decline line counts as accounted-for.
			if (stripos($declines, $field) !== false) {

				continue;
			}

			$unaccounted[] = $field;
		}

		T::equals(
			implode(", ", $unaccounted),
			"",
			"every POST /pages body field is settable, exempt, or declined"
		);

		// And the specific C2 guarantee: trunk is a real body field, and it is declined.
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

			$offset = $m[0][1];

			if (!preg_match('/"body"\s*=>\s*\[(.*?)\]/s', $source, $body_match, 0, $offset)) {

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
