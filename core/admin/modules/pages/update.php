<?
	// Initiate the Upload Service class.
	$upload_service = new BigTreeUploadService;

	$page = $_POST["page"];
	
	if ($page[0] == "p") {
		$change_id = substr($page,1);
		$f = $admin->getPendingChange($change_id);
		$pdata = $f["changes"];
		$r = $admin->getPageAccessLevel($pdata["parent"]);
	} else {
		$r = $admin->getPageAccessLevel($page);
		// Get pending page data with resources decoded and tags.
		$pdata = $cms->getPendingPage($page,true,true);
	}
	
	// Work out the permissions	
	if ($r == "p") {
		$publisher = true;
	} elseif ($r == "e") {
		$publisher = false;
	} else {
?>
<div class="form_container">
	<section>
		<h3>Error</h3>
		<p>You do not have access to this page.</p>
	</section>
</div>
<?
		$admin->stop();
	}
	
	$resources = array();
	$crops = array();
	$fails = array();
	
	// Parse resources
	include BigTree::path("admin/modules/pages/_resource-parse.php");
	// Parse callouts
	include BigTree::path("admin/modules/pages/_callout-parse.php");	
	
	if ($publisher && $_POST["ptype"] == "Save & Publish") {
		// Let's make it happen.
		if ($page[0] == "p") {
			// It's a pending page, so let's create one.
			if (!$_POST["parent"]) {
				$_POST["parent"] = $pdata["parent"];
			}
			
			$page = $admin->createPage($_POST);
			$admin->deletePendingChange($change_id);
			$admin->growl("Pages","Created & Published Page");
		} else {
			// It's an existing page.
			$admin->updatePage($page,$_POST);
			$admin->growl("Pages","Updated Page");
		}
	} else {
		if (!$_POST["parent"]) {
			$_POST["parent"] = $pdata["parent"];
		}
		$admin->submitPageChange($page,$_POST);
		$admin->growl("Pages","Saved Page Draft");
	}
	
	$admin->unlock("bigtree_pages",$page);

	// We can't return to any lower number, so even if we edited the homepage, return to the top level nav.	
	if ($pdata["parent"] == "-1") {
		$pdata["parent"] = 0;
	}
	
	if (count($crops)) {
		if ($_POST["return_to_front"]) {
			$pd = $cms->getPage($page);
			$return_page = WWW_ROOT.$pd["path"]."/";
		} elseif (end($bigtree["path"]) == "preview") {
			$return_page = $cms->getPreviewLink($page)."?bigtree_preview_return=".urlencode(ADMIN_ROOT."pages/edit/$page/");
		} else {
			$return_page = ADMIN_ROOT."pages/view-tree/".$pdata["parent"]."/";
		}
		include BigTree::path("admin/modules/pages/_crop.php");
	} elseif (count($fails)) {
		include BigTree::path("admin/modules/pages/_failed.php");
	} else {
		if (end($bigtree["path"]) == "preview") {
			$admin->ungrowl();
			BigTree::redirect($cms->getPreviewLink($page)."?bigtree_preview_return=".urlencode(ADMIN_ROOT."pages/edit/$page/"));
		} elseif ($_POST["return_to_front"]) {
			$admin->ungrowl();
			if ($page == 0) {
				BigTree::redirect(WWW_ROOT);
			} else {
				$pd = $cms->getPage($page);
				BigTree::redirect(WWW_ROOT.$pd["path"]."/");
			}
		} else {
			BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$pdata["parent"]."/");
		}
	}
?>