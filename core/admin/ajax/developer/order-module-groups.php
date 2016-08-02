<?php
	namespace BigTree;
	
	foreach ($_POST as $id => $position) {
		$group = new ModuleGroup($id);
		$group->Position = $position;
		$group->save();
	}
	