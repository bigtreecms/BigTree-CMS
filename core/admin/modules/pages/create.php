<?php
	namespace BigTree;
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	CSRF::verify();
	
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
	
	// Make sure trunk is only available to developers
	$trunk = Auth::user()->Level < 2 ? "" : $_POST["trunk"];
	
	if ($access_level == "p" && $_POST["form_action"] == "Create & Publish") {
		// Let's make it happen.
		$page = Page::create($trunk, $_POST["parent"], $_POST["in_nav"], $_POST["nav_title"], $_POST["title"],
							 $_POST["route"], $_POST["meta_description"], $_POST["seo_invisible"], $_POST["template"],
							 $_POST["external"], $_POST["new_window"], $_POST["resources"], $_POST["publish_at"],
							 $_POST["expire_at"], $_POST["max_age"], $_POST["tags"]);
		$page_id = $page->ID;
		
		Utils::growl("Pages", "Created & Published Page");
	} else {
		$change = PendingChange::createPage($_POST["trunk"], $_POST["parent"], $_POST["in_nav"], $_POST["nav_title"],
											$_POST["title"], $_POST["route"], $_POST["meta_description"],
											$_POST["seo_invisible"], $_POST["template"], $_POST["external"],
											$_POST["new_window"], $_POST["resources"], $_POST["publish_at"],
											$_POST["expire_at"], $_POST["max_age"], $_POST["_tags"]);
		
		$page_id = "p".$change->ID;
		
		Utils::growl("Pages", "Created Page Draft");
	}
	
	// Track resource allocation
	Resource::allocate("pages", $page_id);
	
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => $page_id,
		"return_link" => ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/",
		"edit_link" => ADMIN_ROOT."pages/edit/$page_id/",
		"errors" => $bigtree["errors"]
	);
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect(ADMIN_ROOT."pages/crop/$page_id/");
	} elseif (count($bigtree["errors"])) {
		Router::redirect(ADMIN_ROOT."pages/error/$page_id/");
	}
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
	