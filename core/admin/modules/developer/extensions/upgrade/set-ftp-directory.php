<?php

	if (!$admin->settingExists('bigtree-internal-ftp-upgrade-root')) {
	    $admin->createSetting(array(
			'id' => 'bigtree-internal-ftp-upgrade-root',
			'system' => 'on',
		));
	}
	$admin->updateSettingValue('bigtree-internal-ftp-upgrade-root', $_POST['ftp_root']);
	BigTree::redirect($page_link.'process/'.$page_vars);
?>