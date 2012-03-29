<?
	foreach ($data[$key] as &$item) {
		$item = json_decode(str_replace($www_root,"{wwwroot}",$item),true);
	}
	
	$value = $data[$key];
?>