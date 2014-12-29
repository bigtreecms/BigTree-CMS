<?
	$manifest = json_decode(file_get_contents(SERVER_ROOT."cache/package/manifest.json"),true);

	// Insert the extension and growl
	$admin->installExtension($manifest);
	$admin->growl("Developer","Installed Extension");

	// If we have an install.php file, run it. We're catching the output buffer to see if install.php has anything to show -- if it doesn't, we'll redirect to the complete screen.
	$install_file_path = SERVER_ROOT."extensions/".$manifest["id"]."/install.php";
	if (file_exists($install_file_path)) {
		ob_clean();
		include $install_file_path;
		$ob_contents = ob_get_contents();
		// If the install file didn't generate any markup, just move on to the completion screen
		if (!$ob_contents) {
			BigTree::redirect(DEVELOPER_ROOT."extensions/install/complete/");
		}
	// No install file, completion screen
	} else {
		BigTree::redirect(DEVELOPER_ROOT."extensions/install/complete/");
	}
?>