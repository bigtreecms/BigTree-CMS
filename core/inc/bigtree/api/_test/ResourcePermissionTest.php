<?php
	use BigTree\Services\PermissionService;

	function test_folder_admin_bypass() {
		$admin = (object)["id" => 1, "level" => 1, "permissions" => []];
		T::equals(PermissionService::userFolderLevel($admin, 999), "p", "level>=1 bypasses folder walk");
		T::ok(PermissionService::userHasFolderAccess($admin, 999, "p"), "admin satisfies p");
	}

	function test_folder_explicit_perm() {
		$user = (object)["id" => 1, "level" => 0, "permissions" => [
			"resources" => [5 => "p", 7 => "e", 9 => "n"],
		]];
		T::equals(PermissionService::userFolderLevel($user, 5), "p", "explicit p");
		T::equals(PermissionService::userFolderLevel($user, 7), "e", "explicit e");
		T::equals(PermissionService::userFolderLevel($user, 9), "n", "explicit n");
	}

	function test_folder_root_default_is_editor() {
		$user = (object)["id" => 1, "level" => 0, "permissions" => []];
		T::equals(PermissionService::userFolderLevel($user, 0), "e", "non-admin at root defaults to e");
		T::ok(PermissionService::userHasFolderAccess($user, 0, "e"), "root default satisfies e");
		T::ok(!PermissionService::userHasFolderAccess($user, 0, "p"), "root default does not satisfy p");
	}

	function test_folder_hierarchy_rank_check() {
		// userHasFolderAccess just compares against rank, so this verifies the comparator
		// even with no folder walking needed.
		$user = (object)["id" => 1, "level" => 0, "permissions" => ["resources" => [10 => "e"]]];
		T::ok(PermissionService::userHasFolderAccess($user, 10, "e"), "editor satisfies editor");
		T::ok(!PermissionService::userHasFolderAccess($user, 10, "p"), "editor does not satisfy publisher");
		T::ok(PermissionService::userHasFolderAccess($user, 10, "n"), "editor satisfies none");
	}
