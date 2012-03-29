<?
	parse_str($_GET["sort"]);
	$max = count($parse);
	
	foreach ($row as $pos => $id) {
		$admin->setModulePosition($id,$max - $pos);
	}
?>