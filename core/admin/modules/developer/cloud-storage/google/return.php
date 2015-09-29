<?php

	$token = $cloud->oAuthSetToken($_GET['code']);
	if ($cloud->OAuthError) {
	    $admin->growl('Google Cloud Storage', $cloud->OAuthError, 'error');
	} else {
	    $admin->growl('Google Cloud Storage', 'Connected');
	}
	$cloud->Settings['google']['active'] = true;
	BigTree::redirect(DEVELOPER_ROOT.'cloud-storage/');
?>