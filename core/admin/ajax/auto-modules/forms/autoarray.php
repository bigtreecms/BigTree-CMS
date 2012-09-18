<?
	header("Content-type: text/javascript");
	
	$field = $_GET["field"];
		
	$items = urldecode($_GET["items"]);
	$items = str_replace("&amp;","&",$items);
	parse_str($items);
	
	$items = array();
	
	foreach ($options as $option) {
		foreach ($option as $key => $val) {
			$option[$key] = str_replace(array(WWW_ROOT,STATIC_ROOT),array("{wwwroot}","{staticroot}"),$val);
		}
		$items[] = $option;
	}

?>
$("<?=$field?>").value = "<?=sqlescape(json_encode($items))?>";