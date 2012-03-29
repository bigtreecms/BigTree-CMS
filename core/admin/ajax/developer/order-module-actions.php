<?
	parse_str($_GET["sort"]);
	$max = count($row);
	
	foreach ($row as $pos => $id) {
		$admin->setModuleActionPosition($id,$max - $pos);
	}
?>