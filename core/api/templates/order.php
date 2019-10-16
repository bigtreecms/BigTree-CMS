<?php
	namespace BigTree;
	
	/*
	 	Function: templates/order
			Sets the position of templates.
		
		Method: POST
	 
		Parameters:
	 		positions - A key => value array of template IDs as keys and positions as values
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["positions" => "array"]);
	
	foreach ($_POST["positions"] as $template_id => $position) {
		DB::update("templates", $template_id, ["position" => intval($position)]);
		AuditTrail::track("config:templates", $template_id, "update", "changed position");
	}
