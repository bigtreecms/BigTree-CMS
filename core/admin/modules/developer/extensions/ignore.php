<?php
	namespace BigTree;
	
	setcookie('bigtree_admin[ignored_extension_updates]['.str_replace(array("[","]"),"",$_GET["id"]).']',true,strtotime("+5 years"),str_replace(DOMAIN,"",WWW_ROOT));
	Utils::growl("Extensions","Ignored Updates");
	
	Router::redirect(DEVELOPER_ROOT."extensions/");
	