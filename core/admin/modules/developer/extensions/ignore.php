<?
	setcookie('bigtree_admin[ignored_extension_updates]['.str_replace(array("[","]"),"",$_GET["id"]).']',true,strtotime("+5 years"),str_replace(DOMAIN,"",WWW_ROOT));
	$admin->growl("Extensions","Ignored Updates");
	BigTree::redirect(DEVELOPER_ROOT."extensions/");			
?>