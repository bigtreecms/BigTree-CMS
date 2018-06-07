<?php
	$admin->verifyCSRFToken();
	
	$storage = new BigTreeStorage(true);
	$storage->store($_FILES["file"]["tmp_name"], $_FILES["file"]["name"], "files/temporary/".$admin->ID."/");
