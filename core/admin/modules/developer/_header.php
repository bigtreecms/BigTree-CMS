<?php
	namespace BigTree;
	
	$developer_root = ADMIN_ROOT."developer/";
	define("DEVELOPER_ROOT", $developer_root);

	Auth::user()->requireLevel(2);
?>
<div class="developer">