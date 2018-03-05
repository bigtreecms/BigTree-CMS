<?php
	namespace BigTree;
	
	include Router::getIncludePath("inc/lib/GoogleAuthenticator.php");
	
	echo Auth\GoogleAuthenticator::verifyCode($_POST["secret"], $_POST["code"]) ? "true" : "false";
