<?
	header("Content-type: text/javascript");
	$field = $_GET["field"];
	
	$tname = $field."_sorttable";
	
	parse_str($_GET["items"]);
	foreach ($$tname as $position => $id) {
		$ids[] = $id;
	}

?>
$("#<?=$field?>").value = "<?=sqlescape(json_encode($ids))?>";