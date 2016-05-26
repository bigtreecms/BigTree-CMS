<?php
	$admin->requireLevel(2);
	
	echo cURL::request("http://www.bigtreecms.org/ajax/extensions/exists/?id=".urlencode($_GET["id"]));