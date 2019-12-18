<?php
	namespace BigTree;
	
	/*
	 	Function: field-types/create
			Creates a field type.
			Also creates the field type's files inside of /custom/admin/field-types/.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the field type (required — must be unique, alphanumeric with dashes and underscores, and less than 127 characters)
			name - The name of the field type (required)
			use_cases - An array of use cases for the field type. Valid values are "callouts", "templates", "modules", "settings". (at least one value required)
			self_draw - A boolean for whether this field will draw its own wrapper (defaults to false)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters([
		"id" => "string",
		"name" => "string",
		"use_cases" => "array"
	]);
	
	$id = $_POST["id"];
	
	if (DB::exists("field-types", $id) || file_exists(SERVER_ROOT."core/admin/field-types/$id/")) {
		API::triggerError("Field Type exists with provided ID.", "field-type:exists", "exists");
	}
	
	if (!ctype_alnum(str_replace(["-", "_"], "", $id)) || strlen($id) > 127) {
		API::triggerError("Invalid ID. ID must be alphanumeric with dashes and underscores and less than 128 characters.",
						  "field-type:invalid", "invalid");
	}
	
	$valid_use_cases = ["callouts", "templates", "modules", "settings"];
	$filtered_use_cases = [];
	
	foreach ($_POST["use_cases"] as $use_case) {
		$use_case = strtolower($use_case);
		
		if (in_array($use_case, $valid_use_cases)) {
			$filtered_use_cases[] = $use_case;
		}
	}
	
	$filtered_use_cases = array_unique($filtered_use_cases);
	
	if (!count($filtered_use_cases)) {
		API::triggerError("You must provide at least one valid use case. Valid use cases are: ".implode(", ", $valid_use_cases),
						  "filed-type:invalid", "invalid");
	}
	
	FieldType::create($id, $_POST["name"], $filtered_use_cases, !empty($_POST["self_draw"]));
	
	API::sendResponse([
		"created" => true,
		"cache" => ["field-types" => ["put" => [API::getFieldTypesCacheObject($id)]]]
	], "Created Field Type");
