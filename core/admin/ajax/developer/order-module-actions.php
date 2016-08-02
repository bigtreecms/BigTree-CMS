<?php
	namespace BigTree;
	
	parse_str($_POST["sort"],$data);
	$max = count($data["row"]);
	
	foreach ($data["row"] as $pos => $id) {
		$action = new ModuleAction($id);
		$action->Position = ($max - $pos);
		$action->save();
	}
	