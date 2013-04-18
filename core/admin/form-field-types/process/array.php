<?
	$value = array();
	foreach ($data[$key] as $item) {
		$value[] = json_decode($item,true);
	}
?>