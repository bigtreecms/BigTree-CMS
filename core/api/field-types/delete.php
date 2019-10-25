<?php
	namespace BigTree;
	
	/*
	 	Function: field-types/delete
			Deletes a field type.
			Also deletes the field type's files.
		
		Method: POST
	 
		Parameters:
	 		id - The ID for the field type (required)
	*/
	
	API::requireLevel(2);
	API::requireMethod("POST");
	API::requireParameters(["id" => "string"]);
	
	$id = $_POST["id"];
	
	if (!DB::exists("field-types", $id)) {
		API::triggerError("Field Type was not found.", "field-type:missing", "missing");
	}
	
	$field_type = new FieldType($id);
	$field_type->delete();
	
	API::sendResponse([
		"deleted" => true,
		"cache" => ["field-types" => ["delete" => [$id]]]
	], "Deleted Field Type");
