<?php
	namespace BigTree;

	if (!empty(Router::$POSTError)) {
		http_response_code(406);
		header("Content-type: text/plain");
		echo Text::translate("The uploaded file exceeded the server's maximum upload size.");
		die();
	}
	
	CSRF::verify();
	
	$storage = new Storage(true);
	$storage->store($_FILES["file"]["tmp_name"], $_FILES["file"]["name"], "files/temporary/".Auth::user()->ID."/");
