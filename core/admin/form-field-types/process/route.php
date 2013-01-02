<?
	// If the form told us to generate a route or if this was a pending entry, re-generate a route.
	if ($data[$key] == "generate" || (isset($edit_id) && !is_numeric($edit_id))) {
		if ($options["not_unique"]) {
			$value = $cms->urlify(strip_tags($data[$options["source"]]));
		} else {
			$oroute = $cms->urlify(strip_tags($data[$options["source"]]));
			$value = $oroute;
			$x = 2;
			while (sqlrows(sqlquery("SELECT * FROM `".$form["table"]."` WHERE `$key` = '".sqlescape($value)."' AND id != '".sqlescape($_POST["id"])."'"))) {
				$value = $oroute."-".$x;
				$x++;
			}
		}
	} else {
		$no_process = true;
	}	
?>