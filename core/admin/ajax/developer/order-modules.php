<?php
	namespace BigTree;
	
	CSRF::verify();
	
	foreach ($_POST as $id => $position) {
		DB::update("modules", $id, ["position" => $position]);
	}
	