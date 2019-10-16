<?php
	namespace BigTree;
	
	Router::setLayout("new");

	$raw_templates = Template::all("position DESC, id ASC", true);
	$templates = [];
	
	foreach ($raw_templates as $item) {
		$templates[] = [
			"name" => $item->Name,
			"id" => $item->ID,
			"routed" => $item->Routed
		];
	}
?>
<template-list :templates="<?=Text::htmlEncode(json_encode($templates))?>"></template-list>
