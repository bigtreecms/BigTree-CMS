<?php
	namespace BigTree;
	
	/**
	 * @global Template $template
	 */
	
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
	
	$bigtree["crops"] = [];
	$bigtree["errors"] = [];
	
	// Parse resources
	if (empty($_POST["external"]) && $_POST["template"] != "!") {
		include Router::getIncludePath("admin/modules/pages/_resource-parse.php");
	}
	
	// Make sure trunk is only available to developers
	$trunk = Auth::user()->Level < 2 ? "" : $_POST["trunk"];
	$og_files = Field::getParsedFilesArray("_open_graph_");
	
	if ($access_level == "p" && $_POST["form_action"] == "Create & Publish") {
		// Let's make it happen.
		$page = Page::create($trunk ? true : false, $_POST["parent"], $_POST["in_nav"] ? true : false, $_POST["nav_title"],
							 $_POST["title"], $_POST["route"], $_POST["meta_description"], $_POST["seo_invisible"] ? true : false, 
							 $_POST["template"], $_POST["external"], $_POST["new_window"], $_POST["resources"], $_POST["publish_at"],
							 $_POST["expire_at"], $_POST["max_age"], $_POST["_tags"]);
		$page_id = $page->ID;
		$did_publish = true;

		OpenGraph::handleData("bigtree_pages", $page_id, $_POST["_open_graph_"], $og_files["image"]);
		Utils::growl("Pages", "Created & Published Page");
	} else {
		$og_change_data = OpenGraph::handleData(null, null, $_POST["_open_graph_"], $og_files["image"], true);
		$change = PendingChange::createPage($_POST["trunk"] ? true : false, $_POST["parent"], $_POST["in_nav"] ? true : false, 
											$_POST["nav_title"], $_POST["title"], $_POST["route"], $_POST["meta_description"],
											$_POST["seo_invisible"] ? true : false, $_POST["template"], $_POST["external"],
											$_POST["new_window"], $_POST["resources"], $_POST["publish_at"],
											$_POST["expire_at"], $_POST["max_age"], $_POST["_tags"], $og_change_data);
		
		$page_id = "p".$change->ID;
		$did_publish = false;
		
		Utils::growl("Pages", "Created Page Draft");
	}
	
	// Run any post-processing hook
	if (!empty($template->Hooks["post"])) {
		call_user_func($template->Hooks["post"], $page_id, $_POST["resources"], $did_publish);
	}
	
	// Track resource allocation
	Resource::allocate("bigtree_pages", $page_id);
	
	$_SESSION["bigtree_admin"]["form_data"] = [
		"page" => $page_id,
		"return_link" => ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/",
		"edit_link" => ADMIN_ROOT."pages/edit/$page_id/",
		"errors" => $bigtree["errors"]
	];
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect(ADMIN_ROOT."pages/crop/$page_id/");
	} elseif (count($bigtree["errors"])) {
		Router::redirect(ADMIN_ROOT."pages/error/$page_id/");
	}
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$_POST["parent"]."/");
	