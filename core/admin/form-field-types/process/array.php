<?
	$value = array();
	// New data, normal module / page data.
	if (is_array($data[$key])) {
		foreach ($data[$key] as $item) {
			if (is_string($item)) {
				$value[] = json_decode($item,true);
			} else {
				$value[] = $item;
			}
		}
	// Callouts are going to keep this as a string.
	} elseif (is_string($data[$key])) {
		$value = json_decode($data[$key],true);
	}
?>