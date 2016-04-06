<?php
	namespace BigTree;
	
	$_SESSION["bigtree_admin"]["developer"]["package"] = array("tables" => array(),"files" => array());
	Router::redirect(DEVELOPER_ROOT."extensions/build/details/");
	