<?php
	/**
	 * Audit #12 A3: the scaffold's module group, resolved at staging and re-checked at
	 * approval.
	 *
	 * `create_module` has resolved its group by id *or* name since audit #9, hands
	 * back a choice (with a `prior_change` hint) when neither matches, and re-reads it
	 * at approval so a group deleted inside the proposal's 24-hour life degrades to
	 * ungrouped-with-a-note rather than filing the module under a dead id — that
	 * re-check is audit #8 A2. `scaffold_module` did none of the three: its argument
	 * description said "module-group id" while its sibling accepted a name, so the
	 * same sentence from the user resolved on one tool and stored a dangling string on
	 * the other.
	 */

	use BigTree\Services\ModuleService;

	/** True when the modules JSONDB store is reachable in this harness. */
	function _scaffold_group_store_ready(): bool {
		try {
			BigTreeJSONDB::getAll("module-groups");

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — module store unavailable: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	function _scaffold_group_args(array $overrides = []): array {

		return array_merge([
			"name" => "ZZ Scaffold Group Probe",
			"table" => "zz_scaffold_group_" . substr(md5((string)mt_rand()), 0, 8),
			"fields" => [["title" => "Headline", "type" => "text"]],
		], $overrides);
	}

	function test_scaffold_resolves_its_group_by_name() {
		if (!_scaffold_group_store_ready()) {

			return;
		}

		$service = new ModuleService();
		$developer = ai_wiring_user(2);
		$group_id = null;

		try {
			$group_id = BigTreeJSONDB::insert("module-groups", [
				"name" => "ZZ Probe Group " . uniqid(),
				"route" => "zz-probe-group-" . uniqid(),
			]);
			BigTreeJSONDB::$Cache = [];
			$group = BigTreeJSONDB::get("module-groups", $group_id);

			$by_name = $service->aiValidateModuleScaffold(
				_scaffold_group_args(["group" => (string)$group["name"]]),
				$developer
			);
			T::ok(!empty($by_name["ok"]), "a scaffold naming an existing group validates");
			T::equals(
				(string)($by_name["payload"]["group"] ?? ""),
				(string)$group_id,
				"and the group's *name* resolved to its id, exactly as create_module resolves it"
			);

			$by_id = $service->aiValidateModuleScaffold(
				_scaffold_group_args(["group" => (string)$group_id]),
				$developer
			);
			T::equals((string)($by_id["payload"]["group"] ?? ""), (string)$group_id, "an id still resolves to itself");

			// A group that matches nothing is a choice handed back, not a dangling
			// string written into the module record.
			$unknown = $service->aiValidateModuleScaffold(
				_scaffold_group_args(["group" => "Definitely Not A Group " . uniqid()]),
				$developer
			);
			T::ok(isset($unknown["needs_input"]), "an unresolvable group asks which one was meant");
			T::equals(
				(string)($unknown["prior_change"]["tool"] ?? ""),
				"create_module_group",
				"and offers the tool that would create it, so the request can sequence"
			);
		} finally {
			if ($group_id !== null) {
				BigTreeJSONDB::delete("module-groups", $group_id);
			}
		}
	}

	/**
	 * The approval-time half: a group that vanished between staging and approval
	 * degrades to ungrouped with the fact disclosed, rather than filing the module
	 * under an id nothing resolves.
	 */
	function test_scaffold_degrades_when_its_group_vanished_before_approval() {
		if (!_scaffold_group_store_ready()) {

			return;
		}

		try {
			SQL::fetchSingle("SELECT 1");
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable: " . $e->getMessage() . ")\n";

			return;
		}

		$service = new ModuleService();
		$developer = ai_wiring_user(2);
		$group_id = null;
		$module_id = null;
		$table = "";

		try {
			$group_id = BigTreeJSONDB::insert("module-groups", [
				"name" => "ZZ Doomed Group " . uniqid(),
				"route" => "zz-doomed-group-" . uniqid(),
			]);
			BigTreeJSONDB::$Cache = [];

			$staged = $service->aiValidateModuleScaffold(
				_scaffold_group_args(["group" => (string)$group_id]),
				$developer
			);
			T::ok(!empty($staged["ok"]), "the scaffold staged against a real group");

			// The 24-hour life of a proposal, compressed.
			BigTreeJSONDB::delete("module-groups", $group_id);
			BigTreeJSONDB::$Cache = [];

			$table = (string)($staged["payload"]["table"] ?? "");

			$result = $service->aiScaffoldModule($staged["payload"], $developer);
			T::equals((string)($result["mode"] ?? ""), "created", "the build still runs — grouping is cosmetic");
			$module_id = (string)($result["id"] ?? "");
			T::ok(
				strpos((string)($result["note"] ?? ""), "created ungrouped") !== false,
				"and the note discloses that it was created ungrouped"
			);

			BigTreeJSONDB::$Cache = [];
			$module = BigTreeJSONDB::get("modules", $module_id);
			T::ok(
				empty($module["group"]),
				"the module is not filed under a group id nothing resolves (got "
					. var_export($module["group"] ?? null, true) . ")"
			);
		} finally {
			if ($module_id !== null && $module_id !== "") {
				BigTreeJSONDB::delete("modules", $module_id);
			}

			if ($table !== "" && BigTree::tableExists($table)) {
				SQL::query("DROP TABLE `{$table}`");
			}

			// Mid-test delete can fail to persist; always re-attempt so a failed
			// assertion before the intentional delete cannot leave the group behind.
			if ($group_id !== null && $group_id !== "" && BigTreeJSONDB::exists("module-groups", $group_id)) {
				BigTreeJSONDB::delete("module-groups", $group_id);
			}
		}
	}
