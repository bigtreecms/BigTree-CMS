<?
	$versions = json_decode($_GET["versions"],true);
	foreach ($versions as $version) {
		setcookie("bigtree_admin[ignored_update][$version]",true,time()+365*60*60*24,str_replace(DOMAIN,"",WWW_ROOT));
	}
	$admin->growl("Developer","Ignored Updates");
	BigTree::redirect(DEVELOPER_ROOT);
?>