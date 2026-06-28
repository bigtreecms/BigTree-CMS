<?php
	use BigTree\Services\PermissionService;
	use BigTree\Api\Exceptions\AuthorizationException;

	function test_permission_level_extraction() {
		T::equals(PermissionService::level((object)["level" => 2]), 2, "object level read");
		T::equals(PermissionService::level(["level" => 3]), 3, "array level read");
		T::equals(PermissionService::level((object)[]), 0, "missing level → 0");
		T::equals(PermissionService::level("nonsense"), 0, "non-user input → 0");
	}

	function test_permission_is_publisher() {
		$editor = (object)["id" => 1, "level" => 0];
		$admin = (object)["id" => 2, "level" => 1];

		T::ok(PermissionService::isPublisher($editor, "p"), "publisher rank can publish");
		T::ok(!PermissionService::isPublisher($editor, "e"), "editor rank cannot publish");
		T::ok(!PermissionService::isPublisher($editor, "n"), "no-access rank cannot publish");
		T::ok(PermissionService::isPublisher($admin, "n"), "global admin can publish regardless of rank");
	}

	function test_permission_assert_can_edit_row() {
		$user = (object)["id" => 1, "level" => 0, "permissions" => [
			"module" => [5 => "n"],
			"module_gbp" => [5 => [10 => "e", 30 => "n"]],
		]];
		$module = ["id" => 5, "gbp" => ["enabled" => true, "group_field" => "category"]];

		// Has access → returns void without throwing.
		PermissionService::assertCanEditRow($user, $module, ["category" => 10]);
		T::ok(true, "assertCanEditRow passes when the user has row access");

		T::throws(function () use ($user, $module) {
			PermissionService::assertCanEditRow($user, $module, ["category" => 30]);
		}, AuthorizationException::class, "assertCanEditRow throws when the user has no row access");
	}

	function test_permission_admin_level_bypass() {
		$user = (object)["id" => 1, "level" => 1, "permissions" => []];
		T::equals(PermissionService::userModuleLevel($user, 5), "p", "level:1+ user gets publisher on any module");
	}

	function test_permission_direct_module_perm() {
		$user = (object)["id" => 1, "level" => 0, "permissions" => ["module" => [5 => "e"]]];
		T::equals(PermissionService::userModuleLevel($user, 5), "e", "direct module perm returned");
		T::ok(PermissionService::userHasModuleAccess($user, 5, "v"), "editor satisfies view");
		T::ok(PermissionService::userHasModuleAccess($user, 5, "e"), "editor satisfies edit");
		T::ok(!PermissionService::userHasModuleAccess($user, 5, "p"), "editor does not satisfy publisher");
	}

	function test_permission_none_is_denied() {
		$user = (object)["id" => 1, "level" => 0, "permissions" => ["module" => [5 => "n"]]];
		T::ok(!PermissionService::userHasModuleAccess($user, 5, "v"), "n-perm user cannot view");
	}

	function test_permission_missing_returns_n() {
		$user = (object)["id" => 1, "level" => 0, "permissions" => []];
		T::equals(PermissionService::userModuleLevel($user, 999), "n", "no perm config returns n");
	}

	function test_permission_gbp_per_row() {
		$user = (object)["id" => 1, "level" => 0, "permissions" => [
			"module" => [5 => "n"],
			"module_gbp" => [5 => [
				10 => "p", 20 => "e", 30 => "n"
			]],
		]];
		$module = ["id" => 5, "gbp" => ["enabled" => true, "group_field" => "category"]];
		T::equals(PermissionService::userRowLevel($user, $module, ["category" => 10]), "p", "gbp row matching group p");
		T::equals(PermissionService::userRowLevel($user, $module, ["category" => 20]), "e", "gbp row matching group e");
		T::equals(PermissionService::userRowLevel($user, $module, ["category" => 30]), "n", "gbp row matching group n");
		T::equals(PermissionService::userRowLevel($user, $module, ["category" => 999]), "n", "gbp row no matching group → n");
	}

	function test_permission_accepts_array_user() {
		$user = ["id" => 1, "level" => 1, "permissions" => []];
		T::equals(PermissionService::userModuleLevel($user, 5), "p", "array-shape user works too");
	}
