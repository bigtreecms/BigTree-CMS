<?php
	namespace BigTree;
	
	foreach ($_POST as $id => $position) {
		SQL::update("bigtree_modules", $id, array("position" => $position));
	}
	