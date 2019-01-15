<?php
	$admin->updateInternalSettingValue("bigtree-internal-ftp-upgrade-root", $_POST["ftp_root"]);
	BigTree::redirect($page_link."process/".$page_vars);
