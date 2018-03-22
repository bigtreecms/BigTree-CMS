<?php
	header("Content-type: text/javascript");
	
	$admin->requireLevel(1);
	$admin->verifyCSRFToken();
	$admin->deleteTag($_GET["id"]);

	echo 'BigTree.growl("Tags","Deleted Tag");';
