<?php
	namespace BigTree;
	
	/*
	 	Function: templates/order
			Sets the position of templates.
		
		Method: POST
	 
		Parameters:
	 		templates - An array of template IDs in their new order
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["templates" => "array"]);
	
	$cache = [];
	$position = count($_POST["templates"]);
	
	foreach ($_POST["templates"] as $template_id) {
		DB::update("templates", $template_id, ["position" => $position]);
		AuditTrail::track("config:templates", $template_id, "update", "changed position");
		
		$cache[] = API::getTemplatesCacheObject($template_id);
		$position--;
	}
	
	API::sendResponse([
		"updated" => true,
		"cache" => ["templates" => ["put" => $cache]]
	], "Ordered Templates");
