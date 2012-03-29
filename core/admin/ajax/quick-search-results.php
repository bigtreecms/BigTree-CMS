<?
	$results = $admin->searchPages($_POST["query"]);
	if (!count($results)) {
		echo '<p>No Results</p>';
	} else {
		echo '<p>Quick Search Results</p>';
		echo '<ul>';
		foreach ($results as $r) {
			$bc = $cms->getBreadcrumbByPage($r);
			$crumbs = array();
			foreach ($bc as $crumb) {
				$crumbs[] = $crumb["title"];
			}
			echo '<li><a href="'.$admin_root."pages/view-tree/".$r["id"].'/" title="'.implode(" &raquo; ",$crumbs).'">'.$r["nav_title"].'</a></li>';
		}
		echo '</ul>';
	}
?>