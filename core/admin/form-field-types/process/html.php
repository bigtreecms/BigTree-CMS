<?php
	$fix_referer = function($matches) {
		$href = str_replace($_SERVER["HTTP_REFERER"],"",$matches[1]);
		return 'href="'.$href.'"';
	};
	// If there are admin links, we want them stripped out and returned back to relative URLs.
	$field["output"] = preg_replace_callback('/href="([^"]*)"/',$fix_referer,$field["input"]);