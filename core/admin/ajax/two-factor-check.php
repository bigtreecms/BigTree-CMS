<?php
	include BigTree::path("inc/lib/GoogleAuthenticator.php");

	echo GoogleAuthenticator::verifyCode($_POST["secret"], $_POST["code"]) ? "true" : "false";
