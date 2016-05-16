<?php
	echo BigTree::cURL("http://www.bigtreecms.org/ajax/extensions/exists/?id=".urlencode($_GET["id"]));