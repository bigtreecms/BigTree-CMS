<?
	
	unset($_SESSION['oauth_token']);
	unset($_SESSION['oauth_token_secret']);
	
	$connection = $instagram = new Instagram(array(
		"apiKey" => $settings["id"],
		"apiSecret" => $settings["secret"],
		"apiCallback" => $mroot . "return/"
	));
	
	$url = $instagram->getLoginUrl();
	BigTree::redirect($url); 
	
?>