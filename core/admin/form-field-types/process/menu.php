<?
	foreach ($data[$key] as &$item) {
		$item = json_decode(str_replace(WWW_ROOT,"{wwwroot}",$item),true);
	}
	
	$value = $data[$key];
?>