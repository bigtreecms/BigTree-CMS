<?
	setcookie('bigtree_admin[deferred_update]',true,time()+7*60*60*24,str_replace(DOMAIN,"",WWW_ROOT));
	$admin->growl("Developer","Ignored Updates For 1 Week");
	BigTree::redirect(DEVELOPER_ROOT);
?>