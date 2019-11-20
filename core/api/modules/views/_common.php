<?php
	namespace BigTree;
	
	// Common header to verify module and view parameters
	API::requireParameters([
		"module" => "string",
		"view" => "string"
	]);
	
	$module_id = isset($_GET["module"]) ? $_GET["module"] : $_POST["module"];
	$view_id = isset($_GET["view"]) ? $_GET["view"] : $_POST["view"];
	
	$module = new Module($module_id, function() {
		API::triggerError("Module was not found.", "module:missing", "missing");
	});
	
	$view = null;
	
	foreach ($module->Views as $item) {
		if ($item->ID == $view_id) {
			$view = $item;
		}
	}
	
	if (is_null($view)) {
		API::triggerError("View was not found.", "module-view:missing", "missing");
	}
