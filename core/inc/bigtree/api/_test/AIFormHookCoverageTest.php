<?php
	/**
	 * Every hook the Module Designer lets a developer configure is
	 * either run by the entry write path or exempt with a written reason.
	 *
	 * The designer offers four form hooks. The remaster wired `post` and `publish`
	 * into every entry writer and left `pre` running nowhere but embed forms — and
	 * nothing noticed, because no test related what the designer *offers* to what the
	 * write path *does*. This one parses the offer straight out of the TSX, so the
	 * next hook added to the designer and left unwired fails here rather than
	 * shipping unwired.
	 */

	use BigTree\Services\AutoModuleService;

	/**
	 * The hook keys FormHooksEditor.tsx's HOOK_FIELDS declares, parsed from source.
	 *
	 * @return list<string>
	 */
	function ai_form_hook_designer_keys(): array {
		$source = (string)@file_get_contents(SERVER_ROOT . "spa/src/components/developer/module-designer/FormHooksEditor.tsx");

		if (!preg_match('/const HOOK_FIELDS[^=]*=\s*\[(.*?)\n\];/s', $source, $block)) {

			return [];
		}

		preg_match_all('/\bkey:\s*"([a-z_]+)"/', $block[1], $keys);

		return array_values(array_unique($keys[1]));
	}

	/**
	 * The method that invokes each hook, and the write seams that must reach it.
	 *
	 * @return array<string,array{invoker:string,callers:list<string>}>
	 */
	function ai_form_hook_invokers(): array {

		return [
			"pre" => [
				"invoker" => "applyFormPreHook",
				"callers" => ["prepareEntryWrite", "aiCreateEntry", "aiUpdateEntry"],
			],
			"post" => [
				"invoker" => "fireFormHooks",
				"callers" => ["create", "update", "aiCreateEntry", "aiUpdateEntry"],
			],
			"publish" => [
				"invoker" => "fireFormHooks",
				"callers" => ["create", "update", "aiCreateEntry", "aiUpdateEntry"],
			],
		];
	}

	/**
	 * Hooks the write path deliberately never runs, with the reason.
	 *
	 * @return array<string,string>
	 */
	function ai_form_hook_exemptions(): array {

		return [
			"edit" => "draw-time only — legacy called it while rendering the form, and the SPA has no server "
				. "draw step for it to run in; no write is affected",
		];
	}

	function test_every_designer_form_hook_is_run_by_the_write_path_or_exempt() {
		$keys = ai_form_hook_designer_keys();
		$invokers = ai_form_hook_invokers();
		$exempt = ai_form_hook_exemptions();

		T::ok(count($keys) >= 4, "HOOK_FIELDS parsed from FormHooksEditor.tsx (" . implode(", ", $keys) . ")");

		$unaccounted = array_values(array_filter($keys, function (string $key) use ($invokers, $exempt): bool {

			return !isset($invokers[$key]) && !isset($exempt[$key]);
		}));

		T::equals(implode(", ", $unaccounted), "", "every designer hook is either run on a write or exempt");

		foreach ($invokers as $key => $wiring) {
			$body = ai_surface_method_body(AutoModuleService::class, $wiring["invoker"]);

			T::ok(
				strpos($body, "[\"{$key}\"]") !== false,
				"{$wiring["invoker"]} actually reads the `{$key}` hook"
			);

			foreach ($wiring["callers"] as $caller) {
				T::ok(
					strpos(ai_surface_method_body(AutoModuleService::class, $caller), "\$this->{$wiring["invoker"]}(") !== false,
					"{$caller} reaches the `{$key}` hook through {$wiring["invoker"]}"
				);
			}
		}
	}

	function test_no_form_hook_classification_is_stale() {
		$keys = ai_form_hook_designer_keys();
		$stale = array_values(array_filter(
			array_merge(array_keys(ai_form_hook_invokers()), array_keys(ai_form_hook_exemptions())),
			function (string $key) use ($keys): bool {

				return !in_array($key, $keys, true);
			}
		));

		T::equals(implode(", ", $stale), "", "every classified hook is still one the designer offers");
		T::equals(
			implode(", ", array_intersect(array_keys(ai_form_hook_invokers()), array_keys(ai_form_hook_exemptions()))),
			"",
			"no hook is both run and exempt"
		);
	}
