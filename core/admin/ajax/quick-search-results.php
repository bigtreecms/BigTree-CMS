<?
	$results = $admin->searchPages($_POST["query"]);
	if (!count($results)) {
		echo '<p class="no_results"><em>No Quick Search Results</em></p>';
		echo '<a class="advanced_search no_results" href="#">Advanced Search</a>';
	} else {
		foreach ($results as $r) {
			$access_level = $admin->getPageAccessLevel($r["id"]);
			if ($access_level) {
				$bc = $cms->getBreadcrumbByPage($r);
				$crumbs = array();
				foreach ($bc as $crumb) {
					$crumbs[] = $crumb["title"];
				}
				echo '<a href="'.ADMIN_ROOT."pages/view-tree/".$r["id"].'/" title="'.implode(" &raquo; ",$crumbs).'">'.$r["nav_title"].'</a>';
			}
		}
		echo '<a class="advanced_search" href="#">Advanced Search</a>';
	}
?>