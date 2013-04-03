<?
	
	$ok = false;
	
	if ($youtubeAPI->Client->Process()) {
		$ok = true;
	}
	
	if (!$ok) {
		$admin->growl("YouTube API", "API Error");
		BigTree::redirect($mroot . "connect/");
	}

?>