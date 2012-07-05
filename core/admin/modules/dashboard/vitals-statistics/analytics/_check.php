<?
	if (!$user || !$pass) {
		BigTree::redirect($mroot."setup/");
	}
	
	if (!$profile) {
		BigTree::redirect($mroot."choose-profile/");
	}
?>