<?php
	namespace BigTree;
	
	/**
	 * @global string $page_link
	 * @global string $page_vars
	 */
	
	Setting::updateValue("bigtree-internal-ftp-upgrade-root", $_POST["ftp_root"], true);
	Router::redirect($page_link."process/".$page_vars);
	