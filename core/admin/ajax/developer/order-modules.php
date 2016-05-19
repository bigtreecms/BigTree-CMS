<?php
	foreach ($_POST as $id => $position) {
		$admin->setModulePosition($id,$position);
	}