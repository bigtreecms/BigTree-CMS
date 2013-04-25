<?
	$value = array();
	// New data, normal module / page data.
	if (is_array($data[$key])) {
		foreach ($data[$key] as $i) {
			if (is_string($i)) {
				$value[] = json_decode($i,true);
			} else {
				$value[] = $i;
			}
		}
	// Callouts are going to keep this as a string.
	} elseif (is_string($data[$key])) {
		$value = json_decode($data[$key],true);
	}
?>