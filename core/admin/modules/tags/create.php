<?php
	$tag_string = trim($_POST["tag"]);

	if (empty($tag_string)) {
		$admin->growl("Tags", "Tag Name Was Empty", "error");
		BigTree::redirect(ADMIN_ROOT."tags/add/");
	}

	if (SQL::exists("bigtree_tags", ["tag" => strtolower(html_entity_decode($tag_string))])) {
		$admin->growl("Tags", "Tag Already Exists", "error");
		BigTree::redirect(ADMIN_ROOT."tags/add/");
	}

	$tag_id = $admin->createTag($tag_string);
	$merge_tags = $_POST["merge_tags"];

	if (is_array($merge_tags) && count($merge_tags)) {
		$admin->mergeTags($tag_id, $merge_tags);
	}

	$admin->growl("Tags", "Created Tag");
	BigTree::redirect(ADMIN_ROOT."tags/");
	