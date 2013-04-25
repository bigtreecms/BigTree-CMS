<?
	
	$ok = false;
	
	if ($flickrAPI->Client->Process()) {
		$ok = true;
	}
	
	if (!$ok) {
		$admin->growl("Flickr API", "API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>