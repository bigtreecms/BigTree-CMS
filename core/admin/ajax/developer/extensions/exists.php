<?php
	$admin->requireLevel(2);
	
	echo BigTree::cURL("http://www.bigtreecms.org/ajax/extensions/exists/?id=".urlencode($_GET["id"]));