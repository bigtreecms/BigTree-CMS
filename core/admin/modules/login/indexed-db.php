<?php
	namespace BigTree;
	
	Router::setLayout("new");
	
	if (!empty($_GET["key"])) {
		$cache_data = Cache::get("org.bigtreecms.login-session", $_GET["key"]);
		Cache::delete("org.bigtreecms.login-session", $_GET["key"]);
	}
		
	//Router::redirect($cache_data["login_redirect"] ?: ADMIN_ROOT);
?>
<script>BigTreeAPI.init();</script>
<div id="progress"></div>