<?php
	// BigTree 4.3 -- prerelease

	SQL::query("UPDATE bigtree_pages SET new_window = '' WHERE new_window = 'No'");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 311);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading database to 4.3 revision 12"
	]);
	