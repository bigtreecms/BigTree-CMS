<?php
	foreach ($_POST as $id => $position) {
		$admin->setModuleGroupPosition($id,$position);
	}