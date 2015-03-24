<?
	$integrity_errors = array();
	$external = $_GET["external"] ? true : false;

	// Recursive method for checking an array of data against an array of resources
	$check_data = function($local_path,$external,$resources,$data_set) {
		global $check_data,$integrity_errors;

		foreach ($resources as $resource_id => $resource) {
			$field = $resource["title"];
			$data = $data_set[$resource["id"] ? $resource["id"] : $resource_id];

			// Text types could be URLs
			if ($resource["type"] == "text" && is_string($data)) {
				// External link
				if (substr($data,0,4) == "http" && strpos($data,WWW_ROOT) === false) {
					// Only check external links if we've requested them
					if ($external) {
						// Strip out hashes, they conflict with urlExists
						if (strpos($data,"#") !== false) {
							$data = substr($data,0,strpos($data,"#") - 1);
						}
						if (!BigTreeAdmin::urlExists($data)) {
							$integrity_errors[$field] = array("a" => array($data));
						}
					}
					// Internal link
				} elseif (substr($data,0,4) == "http") {
					if (!BigTreeAdmin::urlExists($data)) {
						$integrity_errors[$field] = array("a" => array($data));
					}
				}
				// HTML we just run through checkHTML
			} elseif ($resource["type"] == "html") {
				$integrity_errors[$field] = BigTreeAdmin::checkHTML($local_path,$data,$external);
			} elseif ($resource["type"] == "callouts" && is_array($data)) {
				foreach ($data as $callout_data) {
					$callout = BigTreeAdmin::getCallout($callout_data["type"]);
					if ($callout) {
						// We're going to modify the field titles so that it makes more sense when someone is diagnosing the issue
						$callout_resources = array_filter((array)$callout["resources"]);
						foreach ($callout_resources as &$column) {
							// If we have an internal title saved we can give even more context to which matrix entity has the problem
							if ($callout_data["display_title"]) {
								$column["title"] = $field." &raquo; ".$callout_data["display_title"]." &raquo; ".$column["title"];
							} else {
								$column["title"] = $field." &raquo; ".$column["title"];
							}
						}
						$check_data($local_path,$external,$callout["resources"],$callout_data);
					}
				}
			} elseif ($resource["type"] == "matrix" && is_array($data)) {
				foreach ($data as $matrix_data) {
					// We're going to modify the field titles so that it makes more sense when someone is diagnosing the issue
					$columns = array_filter((array)$resource["options"]["columns"]);
					foreach ($columns as &$column) {
						// If we have an internal title saved we can give even more context to which matrix entity has the problem
						if ($matrix_data["__internal-title"]) {
							$column["title"] = $field." &raquo; ".$matrix_data["__internal-title"]." &raquo; ".$column["title"];
						} else {
							$column["title"] = $field." &raquo; ".$column["title"];
						}
					}
					$check_data($local_path,$external,$columns,$matrix_data);
				}
			}
		}
	};
?>