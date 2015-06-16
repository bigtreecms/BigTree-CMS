<?php
	print_r($_POST);
	foreach ($_POST as $id => $position) {
		$admin->setModulePosition($id,$position);
	}