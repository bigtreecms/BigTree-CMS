<?php
	namespace BigTree;
	
	CSRF::verify();
	
	foreach ($_POST as $id => $position) {
		DB::update("module-groups", $id, array("position" => $position));
	}
	