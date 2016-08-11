<?php
	namespace BigTree;
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	$bigtree["layout"] = "front-end";
	$bigtree["crops"] = array();
	$bigtree["errors"] = array();
	
	$page_id = $_POST["page"];
	$page = Page::getPageDraft($page_id);
	$access_level = $page->UserAccessLevel;
	
	// Work out the permissions
	$publisher = false;
	
	if ($access_level == "p") {
		$publisher = true;
	} elseif (!$access_level) {
		Auth::stop("You do not have access to this page.", Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// Resource parser needs a template
	$_POST["template"] = $page->Template;
	
	// Parse resources
	include Router::getIncludePath("admin/modules/pages/_resource-parse.php");
	$page->Resources = $bigtree["entry"];
	
	if ($publisher && $_POST["ptype"] == "Save & Publish") {
		// It's a pending page, so let's create one.
		if ($page_id[0] == "p") {
			// Saving a page with -1 ID causes creation
			$page->save();
			
			// Saving usually deletes pending changes but this wasn't related to the page so manually delete it
			$change = new PendingChange($page->ChangeID);
			$change->delete();
		// It's an existing page.
		} else {
			$page->save();
		}
		
		$refresh_link = Link::get($page->ID);
	} else {
		$page->_Tags = $_POST["_tags"];
		Page::createChangeRequest($page_id, $page->Array);
		$refresh_link = Link::getPreview($page_id);
	}
	
	Lock::remove("bigtree_pages", $page_id);
	
	if (count($bigtree["crops"])) {
		include Router::getIncludePath("admin/modules/pages/_front-end-crop.php");
	} elseif (count($bigtree["errors"])) {
		include Router::getIncludePath("admin/modules/pages/_front-end-error.php");
	} else {
?>
<script>parent.BigTreeBar.refresh("<?=$refresh_link?>");</script>
<?php
	}
