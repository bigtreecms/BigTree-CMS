<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$base_directory = SERVER_ROOT."extensions/".Router::$Commands[0]."/plugins/interfaces/".Router::$Commands[1]."/builder/";
	define("BUILDER_ROOT",DEVELOPER_ROOT."modules/interfaces/build/".htmlspecialchars(Router::$Commands[0])."/".htmlspecialchars(Router::$Commands[1])."/");

	$sub_path = array_slice(Router::$Commands,2);
	list($include_file,Router::$Commands) = Router::getRoutedFileAndCommands($base_directory,$sub_path);

	if (!$include_file) {
		Auth::stop("Failed to load the chosen interface's builder.");
	}

	// If we're editing an existing interface, grab it
	$interface = isset($_GET["id"]) ? new ModuleInterface($_GET["id"]) : false;

	include $include_file;
	