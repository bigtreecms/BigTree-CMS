<?php
	$base_directory = SERVER_ROOT."extensions/".$bigtree["commands"][0]."/plugins/interfaces/".$bigtree["commands"][1]."/builder/";
	$sub_path = array_slice($bigtree["commands"],2);
	list($include_file,$bigtree["commands"]) = BigTree::route($base_directory,$sub_path);
	if (!$include_file) {
		$admin->stop("Failed to load the chosen interface's builder.");
	}

	include $include_file;
?>