<?php
	$admin->verifyCSRFToken();
	$r = $admin->getPageAccessLevel($_POST["id"]);
	
	if ($r == "p" && $admin->canModifyChildren($_POST["id"])) {
		parse_str($_POST["sort"],$data);
		
		$max = count($data["row"]);

		foreach ($data["row"] as $pos => $id) {
			$parent = SQL::fetchSingle("SELECT parent FROM bigtree_pages WHERE id = ?", $id);

			if ($parent == $_POST["id"]) {
				$admin->setPagePosition($id, $max - $pos);
			}
		}
	}
