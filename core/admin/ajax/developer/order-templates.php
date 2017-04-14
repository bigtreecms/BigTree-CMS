<?php
	namespace BigTree;
	
	CSRF::verify();
	
	foreach ($_POST as $id => $position) {
		SQL::update("bigtree_templates", $id, array("position" => $position));
	}
	