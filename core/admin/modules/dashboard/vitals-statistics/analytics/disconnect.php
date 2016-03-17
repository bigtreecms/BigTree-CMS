<?php
	$analytics->disconnect();
	$admin->growl("Analytics","Disconnected");

	BigTree::redirect(MODULE_ROOT);