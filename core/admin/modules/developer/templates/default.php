<?php
	namespace BigTree;
	
	Router::setLayout("new");

	$raw_templates = Template::all("position", "DESC", true);
	$templates = [];
	
	foreach ($raw_templates as $item) {
		$templates[] = [
			"name" => $item["name"],
			"id" => $item["id"],
			"routed" => $item["routed"]
		];
	}
?>
<template-list :templates="<?=Text::htmlEncode(json_encode($templates))?>"></template-list>
