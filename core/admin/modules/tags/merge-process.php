<?php
	if ($_POST["merge_to"] == $_POST["tag_id"]) {
		$admin->growl("Tags", "Can't Merge Tag Into Itself");
		BigTree::redirect(ADMIN_ROOT."tags/");
	}

	$admin->mergeTags($_POST["merge_to"], array($_POST["tag_id"]));
	$admin->growl("Tags", "Merged Tags");

	BigTree::redirect(ADMIN_ROOT."tags/");
