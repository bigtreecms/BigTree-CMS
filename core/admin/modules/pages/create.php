<?php
	namespace BigTree;
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	$parent_page = new Page($_POST["parent"]);
	$access_level = $parent_page->UserAccessLevel;
	
	if ($access_level != "p" && $access_level != "e") {
		Auth::stop("You do not have access to create a child for this page.", Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// Adjust template
	if ($_POST["redirect_lower"]) {
		$_POST["template"] = "!";
		$_POST["external"] = "";
		$_POST["new_window"] = "";
	} elseif ($_POST["external"]) {
		$_POST["template"] = "";
		$_POST["new_window"] = isset($_POST["new_window"]) ? $_POST["new_window"] : "";
	} else {
		$_POST["new_window"] = "";
	}
	
	$bigtree["crops"] = array();
	$bigtree["errors"] = array();
	
	// Parse resources
	include Router::getIncludePath("admin/modules/pages/_resource-parse.php");
	
	if ($access_level == "p" && $_POST["ptype"] == "Create & Publish") {
		// Let's make it happen.
		$page = $admin->createPage($_POST);
		Utils::growl("Pages", "Created & Published Page");
	} else {
		$page = "p".$admin->createPendingPage($_POST);
		Utils::growl("Pages", "Created Page Draft");
	}
	
	// Track resource allocation
	$admin->allocateResources("pages", $page);
	
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => $page,
		"return_link" => ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/",
		"edit_link" => ADMIN_ROOT."pages/edit/$page/",
		"errors" => $bigtree["errors"]
	);
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect(ADMIN_ROOT."pages/crop/$page/");
	} elseif (count($bigtree["errors"])) {
		Router::redirect(ADMIN_ROOT."pages/error/$page/");
	}
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
	