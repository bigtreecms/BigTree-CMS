<?php
	namespace BigTree;
	
	$results = Page::search($_POST["query"]);
	
	if (!count($results)) {
		echo '<p class="no_results"><em>No Quick Search Results</em></p>';
		echo '<a class="advanced_search no_results" href="#">Advanced Search</a>';
	} else {
		foreach ($results as $page) {
			if ($page->UserAccessLevel) {
				$crumbs = array();
				
				foreach ($page->Breadcrumb as $crumb) {
					$crumbs[] = $crumb["title"];
				}
				
				echo '<a href="'.ADMIN_ROOT."pages/view-tree/".$page->ID.'/" title="'.implode(" &raquo; ", $crumbs).'">'.$page->NavigationTitle.'</a>';
			}
		}
		
		echo '<a class="advanced_search" href="#">Advanced Search</a>';
	}
	