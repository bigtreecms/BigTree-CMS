<?php
	$admin->verifyCSRFToken();
	$admin->updateInternalSettingValue("bigtree-internal-ftp-upgrade-root",$_POST["ftp_root"]);

	BigTree::redirect(DEVELOPER_ROOT."upgrade/install/");
