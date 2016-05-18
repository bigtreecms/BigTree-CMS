<?php
	namespace BigTree;

	$base_directory = SERVER_ROOT."extensions/".$bigtree["commands"][0]."/plugins/interfaces/".$bigtree["commands"][1]."/builder/";
	define("BUILDER_ROOT",DEVELOPER_ROOT."modules/interfaces/build/".htmlspecialchars($bigtree["commands"][0])."/".htmlspecialchars($bigtree["commands"][1])."/");

	$sub_path = array_slice($bigtree["commands"],2);
	list($include_file,$bigtree["commands"]) = Router::getRoutedFileAndCommands($base_directory,$sub_path);
	if (!$include_file) {
		$admin->stop("Failed to load the chosen interface's builder.");
	}

	// If we're editing an existing interface, grab it
	$bigtree["interface"] = false;
	if (isset($_GET["id"])) {
		$bigtree["interface"] = \BigTreeAutoModule::getInterface($_GET["id"]);
	}

	include $include_file;
	