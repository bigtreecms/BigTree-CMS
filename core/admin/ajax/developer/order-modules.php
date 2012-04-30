<?
	parse_str($_POST["sort"]);
	$max = count($parse);
	
	foreach ($row as $pos => $id) {
		$admin->setModulePosition($id,$max - $pos);
	}
?>