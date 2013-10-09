<?
	header("Content-type: text/json");

	function _localRecurseNav($parent) {
		global $admin;
		$response = array();
		$children = $admin->getPageChildren($parent);
		foreach ($children as $child) {
			// We're going to use single letter properties to make this as light a JSON load as possible.
			$kid = array("t" => $child["nav_title"],"i" => $child["id"]);
			$grandkids = _localRecurseNav($child["id"]);
			if (count($grandkids)) {
				$kid["c"] = $grandkids;
			}
			$response[$child["id"]] = $kid;
		}
		return $response;
	}

	echo json_encode(_localRecurseNav(0))
?>