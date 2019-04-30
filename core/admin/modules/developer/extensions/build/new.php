<?php
	namespace BigTree;
	
	$_SESSION["bigtree_admin"]["developer"]["package"] = ["tables" => [],"files" => []];
	Router::redirect(DEVELOPER_ROOT."extensions/build/details/");
	