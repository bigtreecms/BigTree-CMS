<?php
	namespace BigTree;
	
	foreach ($_POST as $id => $position) {
		$module = new Module($id);
		$module->Position = $position;
		$module->save();
	}
	