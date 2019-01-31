<?php
	namespace BigTree;
	
	if ($_POST["merge_to"] == $_POST["tag_id"]) {
		Utils::growl("Tags", "Can't Merge Tag Into Itself");
		Router::redirect(ADMIN_ROOT."tags/");
	}

	$tag = new Tag($_POST["merge_to"]);
	$tag->merge($_POST["tag_id"]);

	Utils::growl("Tags", "Merged Tags");
	Router::redirect(ADMIN_ROOT."tags/");
