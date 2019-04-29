<?php
	namespace BigTree;
	
	CSRF::verify();
	Setting::updateValue("bigtree-internal-ftp-upgrade-root", $_POST["ftp_root"], true);
	Router::redirect(DEVELOPER_ROOT."upgrade/install/");
	