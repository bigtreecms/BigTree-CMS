<?php
	namespace BigTree;
	
	Router::setLayout("login");
	
	$site = new Page(0, null, false);
	$security_policy = Setting::value("bigtree-internal-security-policy");
?>
<login-form site_title="<?=$site->NavigationTitle?>"
			remember_disabled="<?=(!empty($security_policy["remember_disabled"]))?>"></login-form>