<?php
	namespace BigTree;
	
	CSRF::verify();
	
	foreach ($_POST as $id => $position) {
		SQL::update("bigtree_module_groups", $id, array("position" => $position));
	}
	