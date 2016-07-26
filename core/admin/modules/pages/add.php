<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$bigtree["form_action"] = "create";
	$bigtree["current_page"] = array("id" => $bigtree["current_page"]["id"]);

	$page = new Page;
	
	include Router::getIncludePath("admin/modules/pages/_form.php");