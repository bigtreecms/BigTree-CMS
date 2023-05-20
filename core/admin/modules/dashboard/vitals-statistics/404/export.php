<?php
	/**
	 * @global BigTreeAdmin $admin
	 */
	
	// Multi-site can only load one site's keys at once
	$active_site = null;
	
	if (!empty($bigtree["config"]["sites"]) && is_array($bigtree["config"]["sites"])) {
		$active_site = !empty($_POST["site_key"]) ? $_POST["site_key"] : BigTree::getCookie("bigtree_admin[active_site]");
		
		if (!$active_site) {
			$keys = array_keys($bigtree["config"]["sites"]);
			$active_site = $keys[0];
		}
		
		$domain = $bigtree["config"]["sites"][$active_site]["www_root"];
		[$pages, $items] = $admin->search404s($_POST["type"], "", 1, $active_site, true);
	} else {
		$domain = WWW_ROOT;
		[$pages, $items] = $admin->search404s($_POST["type"], "", 1, "", true);
	}
	
	$file_name = $_POST["type"]."-report-".date("Y-m-d").($active_site ? "-$active_site" : "").".csv";
	
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=$file_name");
	header("Pragma: no-cache");
	header("Expires: 0");
	
	$output = fopen("php://output", "w");
	fputcsv($output, ["Requests", "404 URL", "Query Variables", "Redirect", "Ignored"]);
	
	foreach ($items as $item) {
		fputcsv($output, [
			$item["requests"],
			$domain.$item["broken_url"],
			$item["get_vars"] ? "?".$item["get_vars"] : "",
			$item["redirect_url"],
			$item["ignored"] ? "Yes" : "No",
		]);
	}
	
	fclose($output);
	die();
