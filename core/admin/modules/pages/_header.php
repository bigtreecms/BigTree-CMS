<?
	$proot = ADMIN_ROOT."pages/";
	$id = isset($_POST["page"]) ? $_POST["page"] : end($bigtree["commands"]);
	$action = $bigtree["module_path"][0];

	// Get the end command as the current working page, only decode resources and get tags if we're editing
	$bigtree["current_page"] = $page = $cms->getPendingPage($id,($action == "edit"),($action == "edit"));

	// Get permissions
	if (is_numeric($id)) {
		$bigtree["access_level"] = $admin->getPageAccessLevel($id);
	// If it's a pending page we want the permission level of its parent page
	} else {
		$bigtree["current_page"]["id"] = $page["id"] = $id;
		$bigtree["access_level"] = $admin->getPageAccessLevel($page["parent"]);
	}

	// Stop the user if they don't have access to this page.
	if (!$bigtree["access_level"] && $id !== false && $action != "view-tree") {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Access Denied</h3>
		</div>
		<p>You do not have access to this page.</p>
	</section>
</div>
<?
		$admin->stop();
	}

	// Create custom breadcrumb
	$bigtree["breadcrumb"] = array(
		array("link" => "pages/", "title" => "Pages"),
		array("link" => "pages/view-tree/0", "title" => "Home")
	);
	if ($id != 0) {
		$bc = $cms->getBreadcrumbByPage($page,true);
		foreach ($bc as $item) {
			$bigtree["breadcrumb"][] = array("link" => "pages/view-tree/".$item["id"], "title" => $item["title"]);
		}
	}

	// Fix the navigation.
	$pages_nav = &$bigtree["nav_tree"]["pages"];
	// Replace all the {id}s in the links.
	foreach ($pages_nav["children"] as &$child) {
		$child["link"] = str_replace("{id}",end($bigtree["commands"]),$child["link"]);
	}
	// Pass the current page into $_GET vars for the edit.
	$pages_nav["children"]["edit"]["get_vars"] = array("return_to_self" => true);
	// Replace the home icon if it's not the parent page.
	if (!$id) {
		$pages_nav["children"]["view-tree"]["icon"] = "home";
		$pages_nav["children"]["view-tree"]["title_override"] = "Home";
		unset($pages_nav["children"]["move"]);
	} else {
		$pages_nav["children"]["view-tree"]["title_override"] = $page["nav_title"];
	}
	// Hide "Move" and "Revisions" if this is a pending page or the user isn't a publisher.
	if (!is_numeric($page["id"]) || $bigtree["access_level"] != "p") {
		unset($pages_nav["children"]["move"]);
		unset($pages_nav["children"]["revisions"]);
	}
	// If the user doesn't have access to this page, take away the nav for it.
	if (!$bigtree["access_level"]) {
		unset($pages_nav["children"]["add"]);
		unset($pages_nav["children"]["edit"]);
	}


	// If we can't find the parent or the current page, stop.
	if (!$page) {
		$bigtree["breadcrumb"] = array(
			array("link" => "pages/", "title" => "Pages"),
			array("link" => "pages/view-tree/0", "title" => "Error")
		);
		$pages_nav["children"]["view-tree"]["icon"] = "page";
		$pages_nav["children"]["view-tree"]["title_override"] = "Error";
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>The page you are trying to access no longer exists.</p>
	</section>
</div>
<?
		$admin->stop();
	}

	// Stop them from getting butchered later.
	unset($child,$pages_nav);
?>