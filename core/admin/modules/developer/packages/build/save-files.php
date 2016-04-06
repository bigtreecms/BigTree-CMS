<?php
	namespace BigTree;
	
	BigTree::globalizePOSTVars();
	sort($files);
	sort($tables);

	$_SESSION["bigtree_admin"]["developer"]["package"]["files"] = $files;
	$_SESSION["bigtree_admin"]["developer"]["package"]["tables"] = $tables;

	Router::redirect(DEVELOPER_ROOT."packages/build/review/");
	