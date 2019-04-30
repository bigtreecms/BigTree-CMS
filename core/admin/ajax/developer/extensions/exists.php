<?php
	namespace BigTree;
	
	echo cURL::request("https://www.bigtreecms.org/ajax/extensions/exists/?id=".urlencode($_GET["id"]));
