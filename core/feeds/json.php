<?php
	namespace BigTree;
	
	/**
	 * @global Feed $feed
	 */
	
	$sort = $feed->Settings["sort"] ? $feed->Settings["sort"] : "id DESC";
	$limit = $feed->Settings["limit"] ? $feed->Settings["limit"] : "15";
	$query = SQL::query("SELECT * FROM `".$feed->Table."` ORDER BY $sort LIMIT $limit");
	
	$json_data = array();
	while ($item = $query->fetch()) {
		foreach ($item as $key => $val) {
			$array_val = @json_decode($val, true);
			$item[$key] = Link::decode(is_array($array_val) ? $array_val : $val);
		}
		
		$entry = array();
	
		foreach ($feed["fields"] as $key => $options) {
			$value = $item[$key];
			
			if ($options["parser"]) {
				$value = Module::runParser($item, $value, $options["parser"]);
			}
			
			$entry[$key] = $value;
		}

		$json_data[] = $entry;
	}
	
	header("Content-type: application/json");
	
	if ($feed->Settings["condensed"]) {
		echo json_encode($json_data, JSON_UNESCAPED_SLASHES);
	} else {
		echo JSON::encode($json_data);
	}
	