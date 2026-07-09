<?php
	/**
	 * Permission parity between BigTreeAdmin (legacy) and PermissionService (new).
	 *
	 * Background: the original plan called for replacing BigTreeAdmin's permission
	 * methods with one-line delegates to the new services. That idea didn't survive
	 * contact with the legacy admin's quirks:
	 *
	 *   - getAccessLevel returns "" when the user has no module entry; the service
	 *     returns "n". Different strict-comparison behavior in PHP.
	 *   - canModifyChildren returns false when a descendant page perm is "n" OR "e";
	 *     the service only checks "n". This is intentional legacy semantics
	 *     (editor-level child pages are protected from sibling-reorder).
	 *
	 * Replacing the legacy bodies would either preserve those quirks (so the
	 * "delegate" becomes long, defeating its purpose) or drift from them (breaking
	 * the deployed admin).
	 *
	 * Decision: keep both implementations. Defend against divergence with this
	 * parity test, which exercises both APIs against identical fixtures and asserts
	 * they answer the *user-visible questions* the same way:
	 *
	 *   - "Can this user view the module?"
	 *   - "Can this user edit at least?"
	 *   - "Can this user publish?"
	 *
	 * Internal return values can differ; outcomes must agree. If anything below
	 * starts failing, either the legacy admin or the service drifted — go figure
	 * out which and fix the deltas.
	 *
	 * Tests are skipped when run in the standalone harness (BigTreeAdmin isn't
	 * loaded there). They run as part of the full test suite via run.php.
	 */

	use BigTree\Services\PermissionService;

	function _parity_skip_if_legacy_unavailable() {
		if (!class_exists("BigTreeAdmin", false)) {
			echo "  (skipped — BigTreeAdmin not loaded in standalone harness)\n";

			return true;
		}

		return false;
	}

	/** Build a fake BigTreeAdmin with Level + Permissions injected. */
	function _parity_legacy_admin($level, array $permissions) {
		$admin = new \BigTreeAdmin();
		$admin->Level = $level;
		$admin->Permissions = $permissions;
		// We need an ID so audit-trail-using paths don't NPE; pick anything truthy.
		$admin->ID = 999;

		return $admin;
	}

	function test_parity_admin_level_bypass() {
		if (_parity_skip_if_legacy_unavailable()) {
			return;
		}

		$user_obj = (object)["id" => 999, "level" => 1, "permissions" => []];
		$legacy = _parity_legacy_admin(1, []);

		// Legacy returns "p" for any module when level > 0
		$legacy_result = $legacy->getAccessLevel(42);
		// Service returns "p" for any module when level > 0
		$service_result = PermissionService::userModuleLevel($user_obj, 42);

		T::equals($legacy_result, "p", "legacy: admin gets 'p' on any module");
		T::equals($service_result, "p", "service: admin gets 'p' on any module");
	}

	function test_parity_explicit_module_publisher() {
		if (_parity_skip_if_legacy_unavailable()) {
			return;
		}

		$perms = ["module" => [42 => "p"]];
		$user_obj = (object)["id" => 999, "level" => 0, "permissions" => $perms];
		$legacy = _parity_legacy_admin(0, $perms);

		$legacy_result = $legacy->getAccessLevel(42);
		$service_result = PermissionService::userModuleLevel($user_obj, 42);

		T::equals($legacy_result, "p", "legacy: explicit p");
		T::equals($service_result, "p", "service: explicit p");
	}

	function test_parity_can_publish_check_consistent() {
		// The user-visible question is "can this user publish?". Both legacy and
		// service must agree on this yes/no across a matrix of permission states.
		if (_parity_skip_if_legacy_unavailable()) {
			return;
		}

		$cases = [
			// [user_level, module_perm, expected_can_publish]
			[2, null, true],         // dev — everything publishes
			[1, null, true],         // admin — everything publishes
			[0, "p", true],          // explicit publisher
			[0, "e", false],         // editor — not publisher
			[0, "v", false],         // viewer — not publisher
			[0, "n", false],         // explicitly denied
			[0, null, false],        // no entry — not publisher
		];

		foreach ($cases as $i => [$level, $perm, $expected]) {
			$perms = $perm === null ? [] : ["module" => [42 => $perm]];
			$user_obj = (object)["id" => 999, "level" => $level, "permissions" => $perms];
			$legacy = _parity_legacy_admin($level, $perms);

			$legacy_says = ($legacy->getAccessLevel(42) === "p");
			$service_says = (PermissionService::userModuleLevel($user_obj, 42) === "p");

			T::equals($legacy_says, $expected, "case $i legacy publish-check: level=$level perm=" . ($perm ?? "null"));
			T::equals($service_says, $expected, "case $i service publish-check: level=$level perm=" . ($perm ?? "null"));
		}
	}

	function test_parity_can_edit_check_consistent() {
		// "Can this user edit?" — true iff perm rank >= editor.
		if (_parity_skip_if_legacy_unavailable()) {
			return;
		}

		$cases = [
			[2, null, true],
			[0, "p", true],
			[0, "e", true],
			[0, "v", false],
			[0, "n", false],
			[0, null, false],
		];

		foreach ($cases as $i => [$level, $perm, $expected]) {
			$perms = $perm === null ? [] : ["module" => [42 => $perm]];
			$user_obj = (object)["id" => 999, "level" => $level, "permissions" => $perms];
			$legacy = _parity_legacy_admin($level, $perms);

			// Legacy returns string; "edit" is "e" or "p". Empty/n/v is not editable.
			$legacy_level = $legacy->getAccessLevel(42);
			$legacy_says = ($legacy_level === "e" || $legacy_level === "p");
			$service_says = PermissionService::userHasModuleAccess($user_obj, 42, "e");

			T::equals($legacy_says, $expected, "case $i legacy edit-check");
			T::equals($service_says, $expected, "case $i service edit-check");
		}
	}

	function test_parity_can_view_check_consistent() {
		// "Can this user view?" — true iff perm rank >= viewer.
		// This is where the "" vs "n" return-value drift would have shown up, so
		// it's the most important parity case.
		if (_parity_skip_if_legacy_unavailable()) {
			return;
		}

		$cases = [
			[2, null, true],
			[0, "p", true],
			[0, "e", true],
			[0, "v", true],
			[0, "n", false],
			[0, null, false],   // No module entry: both should say "no view".
		];

		foreach ($cases as $i => [$level, $perm, $expected]) {
			$perms = $perm === null ? [] : ["module" => [42 => $perm]];
			$user_obj = (object)["id" => 999, "level" => $level, "permissions" => $perms];
			$legacy = _parity_legacy_admin($level, $perms);

			// The semantic "can view" check the legacy admin uses across its files
			// is `if ($level && $level != "n")`. Anything truthy that isn't "n" =
			// at least viewer.
			$legacy_level = $legacy->getAccessLevel(42);
			$legacy_says = ($legacy_level && $legacy_level !== "n");
			$service_says = PermissionService::userHasModuleAccess($user_obj, 42, "v");

			T::equals($legacy_says, $expected, "case $i legacy view-check");
			T::equals($service_says, $expected, "case $i service view-check");
		}
	}

	function test_parity_checkAccess_consistent() {
		// checkAccess is the BigTreeAdmin equivalent of userHasModuleAccess(.., "v")
		// — returns bool. Verify they line up.
		if (_parity_skip_if_legacy_unavailable()) {
			return;
		}

		$cases = [
			[2, null, true],
			[0, "p", true],
			[0, "e", true],
			[0, "v", true],
			[0, "n", false],
			[0, null, false],
		];

		foreach ($cases as $i => [$level, $perm, $expected]) {
			$perms = $perm === null ? [] : ["module" => [42 => $perm]];
			$user_obj = (object)["id" => 999, "level" => $level, "permissions" => $perms];
			$legacy = _parity_legacy_admin($level, $perms);

			$legacy_says = (bool)$legacy->checkAccess(42);
			$service_says = PermissionService::userHasModuleAccess($user_obj, 42, "v");

			T::equals($legacy_says, $expected, "case $i legacy checkAccess");
			T::equals($service_says, $expected, "case $i service userHasModuleAccess");
		}
	}
