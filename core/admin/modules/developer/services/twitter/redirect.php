<?
	
	if ($twitterAPI->Client->Initialize()) {
		if ($twitterAPI->Client->Process()) {
			if ($twitterAPI->Client->access_token) {
				echo 'yay';
			}
		}
	}

?>