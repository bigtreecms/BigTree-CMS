<?
	$page = end($bigtree["path"]);
	
	if (is_numeric($page)) {
		$f = $cms->getPage($page);
		$parent = $f["parent"];
	} else {
		$f = $cms->getPendingPage(substr($page,1));
		$parent = $f["changes"]["parent"];
	}
	
	$admin->deletePage($page);
	
	header("Location: ".ADMIN_ROOT."pages/view-tree/$parent/");
?>
