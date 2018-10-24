<?php
	header("Content-type: text/json");
	define("BIGTREE_NO_QUERY_LOG", true);

	set_error_handler(function($error_number, $error_string, $error_file, $error_line) {
		if ($error_number == E_USER_WARNING || $error_number == E_USER_ERROR) {
			echo BigTree::json([
				"complete" => false,
				"error" => $error_string
			]);
			
			die();
		}
	});
