<?
	$page = end($path);
	
	$pdata = $admin->getPendingPage($page);
	
	if ($page[0] == "p") {
		$r = $admin->getPageAccessLevel($pdata["parent"]);
		if ($pdata) {
			$pdata["id"] = $page;
		}
	} else {
		$r = $admin->getPageAccessLevel($page);
		if ($pdata["changed_applied"]) {
			$show_revert = true;
		}
	}

	$resources = $pdata["resources"];
	$callouts = $pdata["callouts"];
	
	if (!isset($pdata["id"])) {
		$breadcrumb = array(
			array("link" => "pages/", "title" => "Pages"),
			array("link" => "pages/view-tree/0/", "title" => "Home")
		);
?>
<h1><span class="error"></span>Error</h1>
<p class="error">The page you are trying to edit no longer exists.</p>
<?
		$admin->stop();
	}
		
	if ($r == "p") {
		$publisher = true;
	} elseif ($r == "e") {
		$publisher = false;
	} else {
		die("You do not have access to this page.");
	}
	
	if ($page == 0) {
?>
<h1><span class="home"></span>Home</h1>
<?
	} else {
?>
<h1><span class="edit_page"></span><?=$pdata["nav_title"]?></h1>
<?
	}
	
	include BigTree::path("admin/modules/pages/_nav.php");
	include BigTree::path("admin/modules/pages/_properties.php");
	
	// Check for a page lock
	$admin->lockCheck("bigtree_pages",$page,"admin/modules/pages/_locked.php",$_GET["force"]);
	
	// SEO Checks
	$seo = $admin->getPageSEORating($pdata,$resources);
	$seo_rating = $seo["score"];
	$seo_recommendations = $seo["recommendations"];
	$seo_color = $seo["color"];
	
	$action = "update";
	include BigTree::path("admin/modules/pages/_form.php");
?>