<?
	$proot = $admin_root."pages/";
	
	// Get the breadcrumb -- if the last command in the URL is numeric, we're doing something with a page, otherwise we'll let the other actions do it themselves.
	if (is_numeric(end($commands)) || $_POST["page"]) {
		$parent = isset($_POST["page"]) ? $_POST["page"] : end($commands);
		
		if ($parent[0] == "p") {
			// Pending page, get the parent instead.
			$c = $admin->getChange(substr($parent,1));
			$cc = json_decode($c["changes"],true);
			$parent = $cc["parent"];
		}
		
		$bc = $cms->getBreadcrumbByPage($cms->getPage($parent));
		$breadcrumb = array(
			array("link" => "pages/", "title" => "Pages"),
			array("link" => "pages/view-tree/0/", "title" => "Home")
		);
		
		if ($parent != 0) {
			foreach ($bc as $item) {
				$breadcrumb[] = array("link" => "pages/view-tree/".$item["id"]."/", "title" => $item["title"]);
			}
		}
	}
?>