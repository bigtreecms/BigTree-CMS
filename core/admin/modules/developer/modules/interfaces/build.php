<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$base_directory = SERVER_ROOT."extensions/".$bigtree["commands"][0]."/plugins/interfaces/".$bigtree["commands"][1]."/builder/";
	define("BUILDER_ROOT",DEVELOPER_ROOT."modules/interfaces/build/".htmlspecialchars($bigtree["commands"][0])."/".htmlspecialchars($bigtree["commands"][1])."/");

	$sub_path = array_slice($bigtree["commands"],2);
	list($include_file,$bigtree["commands"]) = Router::getRoutedFileAndCommands($base_directory,$sub_path);

	if (!$include_file) {
		Auth::stop("Failed to load the chosen interface's builder.");
	}

	// If we're editing an existing interface, grab it
	$interface = isset($_GET["id"]) ? new ModuleInterface($_GET["id"]) : false;

	include $include_file;
	