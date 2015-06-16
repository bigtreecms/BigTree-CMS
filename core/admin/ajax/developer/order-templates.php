<?php
	foreach ($_POST as $id => $position) {
		$admin->setTemplatePosition($id,$position);
	}