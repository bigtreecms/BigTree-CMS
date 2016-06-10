<?php
	namespace BigTree;

	// Common between packages and extensions so separated out
	include Router::getIncludePath("core/admin/modules/developer/extensions/build/_build-component-array.php");

	Router::redirect(DEVELOPER_ROOT."packages/build/files/");