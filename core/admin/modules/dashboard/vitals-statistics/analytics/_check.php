<?
	if (!$user || !$pass) {
		header("Location: ".$mroot."setup/");
		die();
	}
	
	if (!$profile) {
		header("Location: ".$mroot."choose-profile/");
		die();
	}
?>