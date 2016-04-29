<?php
	namespace BigTree;
	
	$storage = new Storage;
	$amazon = new CloudStorage\Amazon;
	$rackspace = new CloudStorage\Rackspace;
	$google = new CloudStorage\Google;
	