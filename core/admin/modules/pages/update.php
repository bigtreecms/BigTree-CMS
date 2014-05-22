<?
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}

	// Stop random hits to the update page
	if (!isset($_POST["page"])) {
		BigTree::redirect(ADMIN_ROOT."pages/view-tree/0/");
	}
	
	// Check access levels on the page we're trying to modify
	$page = $_POST["page"];
	// Pending page
	if ($page[0] == "p") {
		$pending_change = $admin->getPendingChange(substr($page,1));
		$bigtree["current_page_data"] = $pending_change["changes"];
		$bigtree["access_level"] = $admin->getPageAccessLevel($bigtree["current_page_data"]["parent"]);
	// Live page
	} else {
		$bigtree["access_level"] = $admin->getPageAccessLevel($page);
		// Get pending page data with resources decoded and tags.
		$bigtree["current_page_data"] = $cms->getPendingPage($page,true,true);
	}
	
	// Work out the permissions	
	if (!$bigtree["access_level"]) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>You do not have access to this page.</p>
	</section>
</div>
<?
		$admin->stop();
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
	include BigTree::path("admin/modules/pages/_resource-parse.php");
	
	$id = $_POST["page"];
	
	if ($bigtree["access_level"] == "p" && $_POST["ptype"] == "Save & Publish") {
		// Let's make it happen.
		if ($id[0] == "p") {
			// It's a pending page, so let's create one.
			if (!$_POST["parent"]) {
				$_POST["parent"] = $bigtree["current_page_data"]["parent"];
			}
			
			$admin->deletePendingChange(substr($id,1));
			$id = $admin->createPage($_POST);
			$admin->growl("Pages","Created & Published Page");
		} else {
			// It's an existing page.
			$admin->updatePage($id,$_POST);
			$admin->growl("Pages","Updated Page");
		}
	} else {
		if (!$_POST["parent"]) {
			$_POST["parent"] = $bigtree["current_page_data"]["parent"];
		}
		$admin->submitPageChange($id,$_POST);
		$admin->growl("Pages","Saved Page Draft");
	}
	
	$admin->unlock("bigtree_pages",$id);

	// We can't return to any lower number, so even if we edited the homepage, return to the top level nav.	
	if ($bigtree["current_page_data"]["parent"] == "-1") {
		$bigtree["current_page_data"]["parent"] = 0;
	}
	
	if (isset($_GET["preview"])) {
		$redirect_url = $cms->getPreviewLink($id)."?bigtree_preview_return=".urlencode(ADMIN_ROOT."pages/edit/$id/");
	} elseif ($_POST["return_to_front"]) {
		if ($_POST["ptype"] != "Save & Publish") {
			$redirect_url = $cms->getPreviewLink($id);
		} else {
			$page = $cms->getPage($id);
			if ($page["id"]) {
				$redirect_url = WWW_ROOT.$page["path"]."/";
			} else {
				$redirect_url = WWW_ROOT;
			}
		}
	} elseif ($_POST["return_to_self"]) {
		$redirect_url = ADMIN_ROOT."pages/view-tree/".$bigtree["current_page_data"]["id"]."/";
	} else {
		$redirect_url = ADMIN_ROOT."pages/view-tree/".$bigtree["current_page_data"]["parent"]."/";
	}

	// Track resource allocation
	$admin->allocateResources("pages",$id);

	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => $id,
		"return_link" => $redirect_url,
		"edit_link" => ADMIN_ROOT."pages/edit/$id/",
		"errors" => $bigtree["errors"],
		"crops" => $bigtree["crops"]
	);
	
	if (count($bigtree["crops"])) {
		BigTree::redirect(ADMIN_ROOT."pages/crop/$id/");
	} elseif (count($bigtree["errors"])) {
		BigTree::redirect(ADMIN_ROOT."pages/error/$id/");
	}

	BigTree::redirect($redirect_url);
?>