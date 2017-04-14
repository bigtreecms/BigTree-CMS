<?php
	namespace BigTree;
	
	CSRF::verify();
	
	foreach ($_POST as $id => $position) {
		SQL::update("bigtree_modules", $id, array("position" => $position));
	}
	