<?php
	namespace BigTree;

	Extension::cacheHooks();
	Admin::growl("Extensions", "Refreshed Hooks Cache");
	Router::redirect(DEVELOPER_ROOT."extensions/");
