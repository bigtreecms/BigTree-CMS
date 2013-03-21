<?
	
	unset($_SESSION['oauth_token']);
	unset($_SESSION['oauth_token_secret']);
	
	$connection = new TwitterOAuth($key, $secret);
	$request_token = $connection->getRequestToken( $callback );
	
	$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
	$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
	
	switch ($connection->http_code) {
		case 200:
			$url = $connection->getAuthorizeURL($token);
			BigTree::redirect($url); 
			break;
		default:
			die("Could not connect to Twitter.");
			break;
	}

?>