<?php
	// For simple HTML fields, strip invalid tags
	if (!empty($field["settings"]["simple"]) || (isset($field["settings"]["simple_by_permission"]) && $field["settings"]["simple_by_permission"] > $admin->Level)) {
		$field["input"] = strip_tags($field["input"], "<strong><em><b><i><u><a><p><br>");
	}

	// If there are admin links, we want them stripped out and returned back to relative URLs.
	$field["output"] = preg_replace_callback('/href="([^"]*)"/', function($matches) {
		$href = str_replace($_SERVER["HTTP_REFERER"], "", $matches[1]);
		return 'href="'.$href.'"';
	}, $field["input"]);
