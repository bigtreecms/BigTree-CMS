<?
	if (isset($_SESSION["developer"]["saved_module"])) {
		foreach ($_SESSION["developer"]["saved_module"] as $key => $val) {
			if (substr($key,0,1) != "_") {
				$$key = $val;
			}
			unset($_SESSION["developer"]["saved_module"][$key]);
		}
	} else {
		// Prevent notices
		$name = $group_new = $group_existing = $table = $class = $title = "";
		$view = array("description" => "", "options" => "");
		$type = "searchable";
	}
	
	if (isset($_SESSION["developer"]["designer_errors"])) {
		$e = $_SESSION["developer"]["designer_errors"];
		unset($_SESSION["developer"]["designer_errors"]);
	} else {
		$e = array();
	}
?>