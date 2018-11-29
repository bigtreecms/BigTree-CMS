<?php
	$admin->cacheHooks();
	$admin->growl("Extensions", "Refreshed Hooks Cache");

	BigTree::redirect(DEVELOPER_ROOT."extensions/");
