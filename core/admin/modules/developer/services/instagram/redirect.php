<?
	
	$ok = false;
	
	if ($instagramAPI->Client->Process()) {
		$ok = true;
	}
	
	if (!$ok) {
		$admin->growl("Instagram API", "API Error");
		BigTree::redirect($mroot . "connect/");
	}
	
?>