<?
	$value = array();
	foreach ($data[$key] as $item) {
		if (is_string($item)) {
			$value[] = json_decode($item,true);
		} else {
			$value[] = $item;
		}
	}
?>