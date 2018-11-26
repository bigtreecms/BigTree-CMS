<?php
	// BigTree 4.2.1

	setcookie("bigtree_admin[password]","",time()-3600,str_replace(DOMAIN,"",WWW_ROOT));
	sqlquery("CREATE TABLE `bigtree_user_sessions` (`id` varchar(255) NOT NULL DEFAULT '', `email` varchar(255) DEFAULT NULL, `chain` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), KEY `email` (`email`), KEY `chain` (`chain`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");

	$admin->updateInternalSettingValue("bigtree-internal-revision", 201);
	
	echo BigTree::json([
		"complete" => true,
		"response" => "Upgraded to BigTree 4.2.1"
	]);
