<?php
	namespace BigTree;
	
	Auth::user()->requireLevel(1);
	
	$resource = Resource::getByFile($_POST["file"]);

	if ($resource) {
		$resource->delete();
	}
	