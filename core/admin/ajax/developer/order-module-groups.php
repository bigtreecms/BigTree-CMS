<?
	parse_str($_POST["sort"]);
	$max = count($row);
	
	foreach ($row as $pos => $id) {
		$admin->setModuleGroupPosition($id,$max - $pos);
	}
?>