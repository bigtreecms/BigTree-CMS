<?php
	$admin->Auth->requireLevel(1);
	echo json_encode($admin->getResourceFolderAllocationCounts($_POST["folder"]));