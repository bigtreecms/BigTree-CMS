<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (isset($_POST["page"])) {
		$id = $_POST["page"];
	} elseif (isset($bigtree["commands"][0])) {
		$id = $bigtree["commands"][0];
	} else {
		Router::redirect(ADMIN_ROOT."pages/view-tree/0/");
	}
	
	$id = preg_replace("/[^a-z0-9.]+/i", "", $id);
	$action = $bigtree["module_path"][0];
	
	// Get the end command as the current working page
	$page = Page::getPageDraft($id);
	$bigtree["current_page"] = $page->Array;
	$bigtree["access_level"] = Auth::user()->getAccessLevel($page);
	
	// If we can't find the parent or the current page, stop.
	if (!$page) {
		$bigtree["breadcrumb"] = array(
			array("link" => "pages/", "title" => "Pages"),
			array("link" => "pages/view-tree/0", "title" => "Error")
		);
		$pages_nav["children"]["view-tree"]["icon"] = "page";
		$pages_nav["children"]["view-tree"]["title_override"] = "Error";
		
		Auth::stop("The page you are trying to access no longer exists.", Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// Stop the user if they don't have access to this page.
	if (!$bigtree["access_level"] && $id && $action != "view-tree") {
		Auth::stop("You do not have access to this page.", Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = array(
		array("link" => "pages/", "title" => "Pages"),
		array("link" => "pages/view-tree/0", "title" => "Home")
	);
	
	if ($id != 0) {
		$bc = $page->Breadcrumb;
		
		foreach ($bc as $item) {
			$bigtree["breadcrumb"][] = array("link" => "pages/view-tree/".$item["id"], "title" => $item["title"]);
		}
	}
	
	// Fix the navigation.
	$pages_nav = &$bigtree["nav_tree"]["pages"];
	// Replace all the {id}s in the links.
	foreach ($pages_nav["children"] as &$child) {
		$child["link"] = str_replace("{id}", $id, $child["link"]);
	}
	// Pass the current page into $_GET vars for the edit.
	$pages_nav["children"]["edit"]["get_vars"] = array("return_to_self" => true);
	// Replace the home icon if it's not the parent page.
	if (!$id) {
		$pages_nav["children"]["view-tree"]["icon"] = "home";
		$pages_nav["children"]["view-tree"]["title_override"] = "Home";
		unset($pages_nav["children"]["move"]);
	} else {
		$pages_nav["children"]["view-tree"]["title_override"] = $page->NavigationTitle;
	}
	// Hide "Move" and "Revisions" if this is a pending page or the user isn't a publisher.
	if (!is_numeric($page->ID) || $bigtree["access_level"] != "p") {
		unset($pages_nav["children"]["move"]);
		unset($pages_nav["children"]["revisions"]);
	}
	// If the user doesn't have access to this page, take away the nav for it.
	if (!$bigtree["access_level"]) {
		unset($pages_nav["children"]["add"]);
		unset($pages_nav["children"]["edit"]);
	}
	
	// Stop them from getting butchered later.
	unset($child, $pages_nav);
	