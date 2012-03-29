<?
	if ($data[$key] == "generate") {
		if ($options["not_unique"]) {
			$value = $cms->urlify(strip_tags($data[$options["source"]]));
		} else {
			$oroute = $cms->urlify(strip_tags($data[$options["source"]]));
			$value = $oroute;
			$x = 2;
			while (sqlrows(sqlquery("SELECT * FROM ".$form["table"]." WHERE `$key` = '".mysql_real_escape_string($value)."' AND id != '".$_POST["id"]."'"))) {
				$value = $oroute."-".$x;
				$x++;
			}
		}
	} else {
		$no_process = true;
	}	
?>