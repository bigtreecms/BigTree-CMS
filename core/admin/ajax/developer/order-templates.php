<?php
	namespace BigTree;
	
	foreach ($_POST as $id => $position) {
		$template = new Template($id);
		$template->Position = $position;
		$template->save();
	}
	