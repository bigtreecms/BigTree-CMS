<?php
	if (!empty($admin->POSTError)) {
		http_response_code(406);
		header("Content-type: text/plain");
		echo "The uploaded file exceeded the server's maximum upload size.";
		die();
	}

	$admin->verifyCSRFToken();
	
	$storage = new BigTreeStorage(true);
	$storage->store($_FILES["file"]["tmp_name"], $_FILES["file"]["name"], "files/temporary/".$admin->ID."/");
