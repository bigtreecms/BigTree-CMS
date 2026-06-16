<?php
	use BigTree\Services\ModuleService;
	use BigTree\Services\ModuleActionSourceService;
	use BigTree\Api\Exceptions\BadRequestException;

	/**
	 * Characterization tests for the action-source filesystem cluster of
	 * ModuleService (actionSourcePath / writeActionSource / readActionSource /
	 * deleteActionSource / moveActionSource).
	 *
	 * These are pure-filesystem tests (no DB). They drive the methods by
	 * reflection so they pin behavior both before and after the extraction into
	 * a collaborator. After extraction the same assertions are re-pointed at the
	 * collaborator's public methods (see test_module_action_source_collaborator).
	 *
	 * Every file/dir created lives under custom/admin/modules/<route>/ and is
	 * removed in a finally block.
	 */

	/**
	 * Returns [object, callable invoke($name, ...$args)] for invoking a private
	 * method on the given instance via reflection. Works for both ModuleService
	 * (before extraction) and ModuleActionSourceService (after).
	 */
	function _mas_invoker($instance) {
		$invoke = function ($name, ...$args) use ($instance) {
			$ref = new ReflectionMethod($instance, $name);
			$ref->setAccessible(true);

			return $ref->invoke($instance, ...$args);
		};

		return $invoke;
	}

	/** Recursively remove a directory tree (best effort). */
	function _mas_rmtree($dir) {
		if (!is_dir($dir)) {
			return;
		}

		$entries = scandir($dir);

		foreach ($entries as $entry) {
			if ($entry === "." || $entry === "..") {
				continue;
			}
			$path = $dir . "/" . $entry;

			if (is_dir($path)) {
				_mas_rmtree($path);
			} else {
				@unlink($path);
			}
		}
		@rmdir($dir);
	}

	/**
	 * Shared assertion body. $invoke drives whichever object exposes the
	 * action-source methods; $context_label distinguishes the two runs.
	 */
	function _mas_run_assertions($invoke, $context_label) {
		$route = "btxtest_" . substr(md5(uniqid("", true)), 0, 8);
		$module = ["route" => $route, "extension" => ""];
		$base_dir = SERVER_ROOT . "custom/admin/modules/$route";

		try {
			// actionSourcePath resolves a custom-module action to the expected path.
			$expected_path = SERVER_ROOT . "custom/admin/modules/$route/edit.js";
			$path = $invoke("actionSourcePath", $module, "edit");
			T::equals($path, $expected_path, "$context_label: actionSourcePath resolves custom-module path");

			// readActionSource returns "" when no file exists yet.
			T::equals($invoke("readActionSource", $module, "edit"), "", "$context_label: readActionSource empty when no file");

			// write -> read round-trip returns the exact source.
			$source = "export const view = () => { return 'hi'; }\n// \xC3\xA9";
			$invoke("writeActionSource", $module, "edit", $source);
			T::ok(is_file($expected_path), "$context_label: writeActionSource created the file");
			T::equals($invoke("readActionSource", $module, "edit"), $source, "$context_label: write->read round-trip is exact");

			// moveActionSource relocates the file on a route change.
			$new_expected = SERVER_ROOT . "custom/admin/modules/$route/edit-renamed.js";
			$invoke("moveActionSource", $module, "edit", "edit-renamed");
			T::ok(!is_file($expected_path), "$context_label: moveActionSource removed the old file");
			T::ok(is_file($new_expected), "$context_label: moveActionSource created the new file");
			T::equals($invoke("readActionSource", $module, "edit-renamed"), $source, "$context_label: moved source content preserved");

			// deleteActionSource removes the file.
			$invoke("deleteActionSource", $module, "edit-renamed");
			T::ok(!is_file($new_expected), "$context_label: deleteActionSource removed the file");

			// actionSourcePath returns "" for an empty action route.
			T::equals($invoke("actionSourcePath", $module, ""), "", "$context_label: actionSourcePath empty for empty route");

			// actionSourcePath returns "" for a traversal action route, and
			// writeActionSource then throws BadRequestException.
			T::equals($invoke("actionSourcePath", $module, "../evil"), "", "$context_label: actionSourcePath empty for traversal route");
			T::throws(function () use ($invoke, $module) {
				$invoke("writeActionSource", $module, "../evil", "x");
			}, BadRequestException::class, "$context_label: writeActionSource throws for traversal route");

			// actionSourcePath returns "" when the module route itself is unsafe.
			$bad_module = ["route" => "../evil", "extension" => ""];
			T::equals($invoke("actionSourcePath", $bad_module, "edit"), "", "$context_label: actionSourcePath empty for unsafe module route");
		} finally {
			_mas_rmtree($base_dir);
		}
	}

	/**
	 * Full action-source behavior, driven against the extracted collaborator's
	 * public methods. These assertions are the characterization contract: they
	 * passed against ModuleService's private methods before the extraction and
	 * now pin the same behavior on ModuleActionSourceService.
	 */
	function test_module_action_source_collaborator() {
		$svc = new ModuleActionSourceService();
		$invoke = _mas_invoker($svc);
		_mas_run_assertions($invoke, "ModuleActionSourceService");
	}

	/**
	 * End-to-end delegation through ModuleService: the 4 private wrapper methods
	 * (read/write/delete/move) must still produce identical filesystem behavior,
	 * proving scaffold/updateAction/deleteAction call sites work unchanged.
	 */
	function test_module_action_source_delegation_via_module_service() {
		$svc = new ModuleService();
		$invoke = _mas_invoker($svc);

		$route = "btxtest_" . substr(md5(uniqid("", true)), 0, 8);
		$module = ["route" => $route, "extension" => ""];
		$base_dir = SERVER_ROOT . "custom/admin/modules/$route";
		$file = SERVER_ROOT . "custom/admin/modules/$route/edit.js";

		try {
			T::equals($invoke("readActionSource", $module, "edit"), "", "delegation: read empty when no file");

			$source = "export const view = () => {}\n";
			$invoke("writeActionSource", $module, "edit", $source);
			T::ok(is_file($file), "delegation: write created the file via ModuleService");
			T::equals($invoke("readActionSource", $module, "edit"), $source, "delegation: write->read round-trip via ModuleService");

			$invoke("moveActionSource", $module, "edit", "edit-renamed");
			T::ok(!is_file($file), "delegation: move removed the old file via ModuleService");
			T::ok(is_file(SERVER_ROOT . "custom/admin/modules/$route/edit-renamed.js"), "delegation: move created the new file via ModuleService");

			$invoke("deleteActionSource", $module, "edit-renamed");
			T::ok(!is_file(SERVER_ROOT . "custom/admin/modules/$route/edit-renamed.js"), "delegation: delete removed the file via ModuleService");

			T::throws(function () use ($invoke, $module) {
				$invoke("writeActionSource", $module, "../evil", "x");
			}, BadRequestException::class, "delegation: write throws for traversal route via ModuleService");
		} finally {
			_mas_rmtree($base_dir);
		}
	}
