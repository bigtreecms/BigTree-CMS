<?php
	echo BigTree::curl("https://www.bigtreecms.org/ajax/extensions/exists/?id=".urlencode($_GET["id"]));
