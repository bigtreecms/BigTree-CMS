<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$storage = new Storage(true);
	$storage->store($_FILES["file"]["tmp_name"], $_FILES["file"]["name"], "files/temporary/".Auth::user()->ID."/");
