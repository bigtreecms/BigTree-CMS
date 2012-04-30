<?
	parse_str($_POST["sort"]);
	$max = count($row);
	
	foreach ($row as $pos => $id) {
		$id = $_POST["rel"][$id];
		$admin->setCalloutPosition($id,$max - $pos);
	}
?>