<?php
	namespace BigTree;
	
	// For simple HTML fields, strip invalid tags
	if (!empty($this->Settings["simple"]) || (isset($this->Settings["simple_by_permission"]) && $this->Settings["simple_by_permission"] > Auth::user()->Level)) {
		$this->Input = strip_tags($this->Input, "<strong><em><b><i><u><a><p><br>");
	}

	// If there are admin links, we want them stripped out and returned back to relative URLs.
	$this->Output = preg_replace_callback('/href="([^"]*)"/', function($matches) {
		$href = str_replace($_SERVER["HTTP_REFERER"], "", $matches[1]);
		return 'href="'.$href.'"';
	}, $this->Input);
