<?php
	namespace BigTree;

	$sort = $feed["options"]["sort"] ? $feed["options"]["sort"] : "id DESC";
	$limit = $feed["options"]["limit"] ? $feed["options"]["limit"] : "15";
	$query = SQL::query("SELECT * FROM `".$feed["table"]."` ORDER BY $sort LIMIT $limit");
	
	$json_data = array();
	while ($item = $query->fetch()) {
		foreach ($item as $key => $val) {
			$array_val = @json_decode($val,true);

			if (is_array($array_val)) {
				$item[$key] = BigTree::untranslateArray($array_val);
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
		echo JSON::encode($json_data);
	}
	