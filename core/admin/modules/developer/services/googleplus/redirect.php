<?
	
	$ok = false;
	
	if ($googleplusAPI->Client->Process()) {
		$ok = true;
	}
	
	if (!$ok) {
		$admin->growl("Google+ API", "API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>