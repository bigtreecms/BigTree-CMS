<?
	
	$ok = false;
	
	if ($twitterAPI->Client->Process()) {
		$ok = true;
	}
	
	if (!$ok) {
		$admin->growl("Twitter API", "API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>