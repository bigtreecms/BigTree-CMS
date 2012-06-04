<?
	foreach ($data[$key] as &$item) {
		$item = json_decode(str_replace(array(WWW_ROOT,STATIC_ROOT),array("{wwwroot}","{staticroot}"),$item),true);
	}
	
	$value = $data[$key];
?>