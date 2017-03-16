<?
	$admin->verifyCSRFToken();
	echo $admin->createTag($_POST["tag"]);
?>