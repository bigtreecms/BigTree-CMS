<?php
	namespace BigTree;
	
	header("Content-type: text/json");

	$recurse_nav = function($parent) {
		global $recurse_nav,$admin;
		
		$page = new Page($parent);
		$response = array();
		
		foreach ($page->Children as $child) {
			// We're going to use single letter properties to make this as light a JSON load as possible.
			$kid = array("t" => $child->NavigationTitle, "i" => $child->ID);
			$grandkids = $recurse_nav($child->ID);
			
			if (count($grandkids)) {
				$kid["c"] = $grandkids;
			}
		
			$response[$child->ID] = $kid;
		}
		
		return $response;
	};

	echo json_encode($recurse_nav(0));
	