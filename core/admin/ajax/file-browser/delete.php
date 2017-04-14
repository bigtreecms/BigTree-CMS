<?php
	namespace BigTree;
	
	CSRF::verify();
	Auth::user()->requireLevel(1);
	
	$resource = Resource::getByFile($_POST["file"]);

	if ($resource) {
		$resource->delete();
	}
	