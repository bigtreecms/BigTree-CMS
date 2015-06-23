<?php
	$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id desc";
	$limit = $feed["options"]["limit"] ? $feed["options"]["limit"] : "15";
	$q = sqlquery("SELECT * FROM ".$feed["table"]." ORDER BY $sort LIMIT $limit");
	
	$json_data = array();
	while ($item = sqlfetch($q)) {
		foreach ($item as $key => $val) {
			if (is_array(json_decode($val,true))) {
				$item[$key] = BigTree::untranslateArray(json_decode($val,true));
			} else {
				$item[$key] = $cms->replaceInternalPageLinks($val);
			}
		}

		$entry = array();
		foreach ($feed["fields"] as $key => $options) {
			$value = $item[$key];
			if ($options["parser"]) {
				$value = BigTree::runParser($item,$value,$options["parser"]);
			}
			$entry[$key] = $value;
		}
		$json_data[] = $entry;
	}

	header("Content-type: application/json");
	if ($feed["options"]["condensed"]) {
		echo json_encode($json_data,JSON_UNESCAPED_SLASHES);
	} else {
		echo BigTree::json($json_data);
	}
?>