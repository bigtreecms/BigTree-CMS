<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$page = new Page($_POST["id"]);
	
	if ($page->UserAccessLevel == "p") {
		parse_str($_POST["sort"], $data);
		$max = count($data["row"]);

		foreach ($data["row"] as $pos => $id) {
			SQL::update("bigtree_pages", $id, ["position" => $max - $pos]);
		}
	}
	