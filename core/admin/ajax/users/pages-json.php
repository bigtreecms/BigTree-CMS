<?php
	header("Content-type: text/json");

	$recurse_nav = function($parent) {
		global $recurse_nav,$admin;
		$response = array();
		$children = $admin->getPageChildren($parent);
		foreach ($children as $child) {
			// We're going to use single letter properties to make this as light a JSON load as possible.
			$kid = array("t" => $child["nav_title"],"i" => $child["id"]);
			$grandkids = $recurse_nav($child["id"]);
			if (count($grandkids)) {
				$kid["c"] = $grandkids;
			}
			$response[$child["id"]] = $kid;
		}
		return $response;
	};

	echo json_encode($recurse_nav(0));