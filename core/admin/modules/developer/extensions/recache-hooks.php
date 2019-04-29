<?php
	namespace BigTree;

	Extension::cacheHooks();
	Utils::growl("Extensions", "Refreshed Hooks Cache");
	Router::redirect(DEVELOPER_ROOT."extensions/");
