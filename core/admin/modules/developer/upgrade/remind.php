<?php
	namespace BigTree;
	
	setcookie('bigtree_admin[deferred_update]',true,time()+7*60*60*24,str_replace(DOMAIN,"",WWW_ROOT));
	
	Admin::growl("Developer","Ignored Updates For 1 Week");
	Router::redirect(DEVELOPER_ROOT);
	