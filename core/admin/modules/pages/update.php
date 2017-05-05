<?php
	namespace BigTree;
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	// Stop random hits to the update page
	if (!isset($_POST["page"])) {
		Router::redirect(ADMIN_ROOT."pages/view-tree/0/");
	}
	
	CSRF::verify();
	
	// Check access levels on the page we're trying to modify
	$page = Page::getPageDraft($_POST["page"]);
	$access_level = $page->UserAccessLevel;
	
	// Work out the permissions	
	if (!$access_level) {
		Auth::stop("You do not have access to this page.", Router::getIncludePath("admin/layouts/_error.php"));
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
	if (empty($_POST["external"]) && $_POST["template"] != "!") {
		include Router::getIncludePath("admin/modules/pages/_resource-parse.php");
	}
	
	// Handle permissions on trunk
	$trunk = (Auth::user()->Level < 2) ? $page->Trunk : $_POST["trunk"];
	$id = $_POST["page"];
	
	if ($access_level == "p" && $_POST["form_action"] == "Save & Publish") {
		// Let's make it happen.
		if ($id[0] == "p") {
			// It's a pending page, so let's create one.
			if (!$_POST["parent"]) {
				$_POST["parent"] = $bigtree["current_page_data"]["parent"];
			}
			
			$id = Page::create($trunk, $_POST["parent"], $_POST["in_nav"], $_POST["nav_title"], $_POST["title"],
							   $_POST["route"], $_POST["meta_description"], $_POST["seo_invisible"], $_POST["template"],
							   $_POST["external"], $_POST["new_window"], $_POST["resources"], $_POST["publish_at"],
							   $_POST["expire_at"], $_POST["max_age"], $_POST["_tags"]);
			
			$change = new PendingChange(substr($id, 1));
			$change->delete();
			
			Utils::growl("Pages", "Created & Published Page");
		} else {
			// It's an existing page.
			$page->update($trunk, $_POST["parent"], $_POST["in_nav"], $_POST["nav_title"], $_POST["title"],
						  $_POST["route"], $_POST["meta_description"], $_POST["seo_invisible"], $_POST["template"],
						  $_POST["external"], $_POST["new_window"], $_POST["resources"], $_POST["publish_at"],
						  $_POST["expire_at"], $_POST["max_age"], $_POST["_tags"]);
			
			Utils::growl("Pages", "Updated Page");
		}
	} else {
		if (!$_POST["parent"]) {
			$_POST["parent"] = $bigtree["current_page_data"]["parent"];
		}
		
		Page::createChangeRequest($id, $_POST);
		Utils::growl("Pages", "Saved Page Draft");
	}
	
	Lock::remove("bigtree_pages", $id);
	
	// We can't return to any lower number, so even if we edited the homepage, return to the top level nav.	
	if ($page->Parent == -1) {
		$page->Parent = 0;
	}
	
	if (isset($_GET["preview"])) {
		$redirect_url = Link::getPreview($id)."?bigtree_preview_return=".urlencode(ADMIN_ROOT."pages/edit/$id/");
	} elseif ($_POST["return_to_front"]) {
		if ($_POST["form_action"] != "Save & Publish") {
			$redirect_url = Link::getPreview($id);
		} else {
			$redirect_url = Link::get($id);
		}
	} elseif ($_POST["return_to_self"]) {
		$redirect_url = ADMIN_ROOT."pages/view-tree/$id/";
	} else {
		$redirect_url = ADMIN_ROOT."pages/view-tree/".$page->Parent."/";
	}
	
	// Track resource allocation
	Resource::allocate("pages", $id);
	
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => $id,
		"return_link" => $redirect_url,
		"edit_link" => ADMIN_ROOT."pages/edit/$id/",
		"errors" => $bigtree["errors"]
	);
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect(ADMIN_ROOT."pages/crop/$id/");
	} elseif (count($bigtree["errors"])) {
		Router::redirect(ADMIN_ROOT."pages/error/$id/");
	}
	
	Router::redirect($redirect_url);
	