<?
	header("Content-type: text/javascript");
	
	$field = $_GET["field"];
		
	$items = urldecode($_GET["items"]);
	$items = str_replace("&amp;","&",$items);
	parse_str($items);
	
	$items = array();
	
	foreach ($options as $option) {
		foreach ($option as $key => $val)
			$option[$key] = str_replace($config["www_root"],"{wwwroot}",$val);
		$items[] = $option;
	}

?>
$("<?=$field?>").value = "<?=mysql_real_escape_string(json_encode($items))?>";